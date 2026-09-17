<?php
declare(strict_types=1);

namespace WordPressFetch\Queue;

use WordPressFetch\Feed\HttpClient;
use WordPressFetch\Feed\Parser;
use WordPressFetch\Import\Importer;
use WordPressFetch\Logging\Logger;
use WordPressFetch\Notifications\Notifier;
use WordPressFetch\Repository\SourceRepository;

final class Worker {
	public function __construct( private readonly Queue $queue, private readonly SourceRepository $sources, private readonly HttpClient $http, private readonly Parser $parser, private readonly Importer $importer, private readonly Logger $logger, private readonly Notifier $notifier ) {}

	/** @return array{processed:int,completed:int,failed:int} */
	public function run( int $limit = 10 ): array {
		$stats  = array(
			'processed' => 0,
			'completed' => 0,
			'failed'    => 0,
		);
		$worker = php_uname( 'n' ) . ':' . getmypid();
		for ( $i = 0; $i < max( 1, min( 100, $limit ) ); $i++ ) {
			$job = $this->queue->claim( $worker );
			if ( ! $job ) {
				break;
			} ++$stats['processed'];
			$result = $this->process( $job );
			if ( is_wp_error( $result ) ) {
				++$stats['failed'];
				$data        = $result->get_error_data();
				$retryable   = ! in_array( $result->get_error_code(), array( 'wpfetch_unsafe_url', 'wpfetch_invalid_xml', 'wpfetch_unsupported_feed', 'wpfetch_http_401', 'wpfetch_http_403', 'wpfetch_http_404', 'wpfetch_http_410' ), true );
				$retry_after = is_array( $data ) && ! empty( $data['retry_after'] ) ? $this->retryAfter( (string) $data['retry_after'] ) : null;
				$this->queue->fail( (int) $job['id'], (string) $job['lock_token'], $result->get_error_message(), $retryable, $retry_after );
			} else {
				++$stats['completed'];
				$this->queue->complete( (int) $job['id'], (string) $job['lock_token'], $result ); }
		}
		return $stats;
	}

	/**
	 * @param array<string,mixed> $job Claimed job.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function process( array $job ): array|\WP_Error {
		$source = $this->sources->find( (int) $job['source_id'] );
		if ( ! $source ) {
			return new \WP_Error( 'wpfetch_source_missing', __( 'The source no longer exists.', 'wordpress-fetch' ) ); }
		if ( 'fetch' !== $job['type'] && 'dry_run' !== $job['type'] ) {
			return new \WP_Error( 'wpfetch_job_type', __( 'Unsupported queue operation.', 'wordpress-fetch' ) ); }
		$response = $this->http->fetch( $source->feedUrl, $source );
		if ( is_wp_error( $response ) ) {
			$this->recordFailure( $source->id, $response, (int) $job['id'] );
			return $response; }
		if ( 304 === $response['status'] ) {
			return array( 'not_modified' => true ); }
		$feed = $this->parser->parse( $response['body'] );
		if ( is_wp_error( $feed ) ) {
			$this->recordFailure( $source->id, $feed, (int) $job['id'] );
			return $feed; }
		$settings = wp_parse_args( get_option( 'wpfetch_settings', array() ), \WordPressFetch\Core\Activator::defaults() );
		$items    = array_slice( $feed['items'], 0, (int) $settings['max_items_fetch'] );
		$counts   = array(
			'discovered' => count( $feed['items'] ),
			'imported'   => 0,
			'updated'    => 0,
			'duplicates' => 0,
			'rejected'   => 0,
			'failed'     => 0,
		);
		foreach ( array_slice( $items, 0, (int) $settings['max_imports_run'] ) as $item ) {
			$result = $this->importer->import( $source, $item, 'dry_run' === $job['type'] );
			if ( is_wp_error( $result ) ) {
				++$counts['failed'];
				continue;
			} $key = match ( $result['state'] ) {
				'imported', 'would_import' => 'imported', 'updated', 'would_update' => 'updated', 'duplicate' => 'duplicates', 'rejected' => 'rejected', default => 'failed' };
			++$counts[ $key ]; }
		$this->recordSuccess( $source->id, $response, $feed['type'] );
		$this->logger->log( 'info', 'fetch_completed', sprintf( 'Parsed %d entries; imported %d.', $counts['discovered'], $counts['imported'] ), $counts, $source->id, (int) $job['id'] );
		return $counts + array(
			'feed_type'  => $feed['type'],
			'feed_title' => $feed['title'],
		);
	}

	/** @param array<string,mixed> $response */ private function recordSuccess( int $source_id, array $response, string $type ): void {
		global $wpdb;
		$wpdb->update(
			\WordPressFetch\Infrastructure\Database\Schema::table( 'sources' ),
			array(
				'detected_type' => $type,
				'health'        => 'healthy',
				'failure_count' => 0,
				'last_fetch'    => current_time( 'mysql', true ),
				'last_success'  => current_time( 'mysql', true ),
				'etag'          => $response['headers']['etag'] ?? null,
				'last_modified' => $response['headers']['last-modified'] ?? null,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $source_id )
		); }
	private function recordFailure( int $source_id, \WP_Error $error, int $job_id ): void {
		global $wpdb;
		$table = \WordPressFetch\Infrastructure\Database\Schema::table( 'sources' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET failure_count=failure_count+1, health=%s, last_fetch=%s, updated_at=%s WHERE id=%d", str_contains( $error->get_error_code(), '401' ) ? 'authentication_error' : ( str_contains( $error->get_error_code(), '429' ) ? 'rate_limited' : 'warning' ), current_time( 'mysql', true ), current_time( 'mysql', true ), $source_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
		$this->logger->log( 'error', 'fetch_failed', $error->get_error_message(), array( 'code' => $error->get_error_code() ), $source_id, $job_id );
		$this->notifier->notify( 'source_failure_' . $source_id, __( 'WordPress Fetch source needs attention', 'wordpress-fetch' ), $error->get_error_message() ); }
	private function retryAfter( string $value ): int {
		if ( ctype_digit( $value ) ) {
			return min( 86400, (int) $value );
		} $time = strtotime( $value );
		return $time ? max( 60, min( 86400, $time - time() ) ) : 300; }
}
