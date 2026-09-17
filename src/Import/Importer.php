<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

use WordPressFetch\Domain\FeedItem;
use WordPressFetch\Domain\Source;
use WordPressFetch\Infrastructure\Database\Schema;
use WordPressFetch\Logging\Logger;

final class Importer {
	public function __construct( private readonly UrlNormalizer $urls, private readonly MappingEngine $mapping, private readonly RuleEngine $rules, private readonly MediaHandler $media, private readonly Logger $logger ) {}

	/** @return array{state:string,post_id:int,reason?:string}|\WP_Error */
	public function import( Source $source, FeedItem $item, bool $dry_run = false ): array|\WP_Error {
		global $wpdb;
		$config     = $source->config;
		$serialized = $item->jsonSerialize();
		if ( ! empty( $config['rules'] ) && is_array( $config['rules'] ) && ! $this->rules->matches( $config['rules'], $serialized ) ) {
			return array(
				'state'   => 'rejected',
				'post_id' => 0,
				'reason'  => 'rules',
			); }
		$normalized_url = $this->urls->normalize( $item->originalUrl );
		$identity       = $item->guid ? $item->guid : ( $normalized_url ? $normalized_url : $item->fingerprint() );
		$guid_hash      = hash( 'sha256', $identity );
		$url_hash       = hash( 'sha256', $normalized_url ? $normalized_url : 'guid:' . $source->id . ':' . $guid_hash );
		$content_hash   = $item->fingerprint();
		$table          = Schema::table( 'imports' );
		if ( ! empty( $config['dedupe_content'] ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE (source_id=%d AND guid_hash=%s) OR url_hash=%s OR content_hash=%s LIMIT 1", $source->id, $guid_hash, $url_hash, $content_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE (source_id=%d AND guid_hash=%s) OR url_hash=%s LIMIT 1", $source->id, $guid_hash, $url_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $dry_run ) {
			return array(
				'state'   => $existing ? ( 'update' === ( $config['duplicate_policy'] ?? 'skip' ) ? 'would_update' : 'duplicate' ) : 'would_import',
				'post_id' => (int) ( $existing['post_id'] ?? 0 ),
			); }
		if ( $existing && 'update' !== ( $config['duplicate_policy'] ?? 'skip' ) ) {
			return array(
				'state'   => 'duplicate',
				'post_id' => (int) $existing['post_id'],
			); }
		$now = current_time( 'mysql', true );
		if ( ! $existing ) {
			$sql = $wpdb->prepare( "INSERT IGNORE INTO {$table} (source_id,guid_hash,url_hash,content_hash,original_guid,original_url,normalized_url,state,created_at,updated_at) VALUES (%d,%s,%s,%s,%s,%s,%s,'importing',%s,%s)", $source->id, $guid_hash, $url_hash, $content_hash, $item->guid, $item->originalUrl, $normalized_url, $now, $now ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$record_id = (int) $wpdb->insert_id;
			if ( 0 === $record_id ) {
				$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE (source_id=%d AND guid_hash=%s) OR url_hash=%s LIMIT 1", $source->id, $guid_hash, $url_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
				return array(
					'state'   => 'duplicate',
					'post_id' => (int) ( $existing['post_id'] ?? 0 ),
				); }
		} else {
			$record_id = (int) $existing['id']; }
		$post_data = $this->mapping->map( $source, $item );
		$post_id   = (int) ( $existing['post_id'] ?? 0 );
		if ( $post_id > 0 && get_post( $post_id ) ) {
			if ( get_post_meta( $post_id, '_wpfetch_sync_locked', true ) ) {
				return array(
					'state'   => 'duplicate',
					'post_id' => $post_id,
					'reason'  => 'editorial_lock',
				);
			} $protected = array_filter( array_unique( array_merge( explode( ',', (string) ( $existing['protected_fields'] ?? '' ) ), (array) get_post_meta( $post_id, '_wpfetch_protected_fields', true ) ) ) );
			foreach ( $protected as $field ) {
				unset( $post_data[ 'post_' . $field ] );
			} $post_data['ID'] = $post_id;
			$result            = wp_update_post( wp_slash( $post_data ), true );
			$state             = 'updated'; } else {
			$result = wp_insert_post( wp_slash( $post_data ), true );
			$state  = 'imported'; }
			if ( is_wp_error( $result ) ) {
				$wpdb->update(
					$table,
					array(
						'state'      => 'failed',
						'error'      => $result->get_error_message(),
						'updated_at' => $now,
					),
					array( 'id' => $record_id )
				);
				return $result; }
			$post_id = (int) $result;
			update_post_meta( $post_id, '_wpfetch_source_id', $source->id );
			update_post_meta( $post_id, '_wpfetch_import_id', $record_id );
			update_post_meta( $post_id, '_wpfetch_original_url', esc_url_raw( $item->originalUrl ) );
			update_post_meta( $post_id, '_wpfetch_guid', sanitize_text_field( $item->guid ) );
			$protected_fields = (array) get_post_meta( $post_id, '_wpfetch_protected_fields', true );
			$seo              = (array) ( $config['seo'] ?? array() );
			$canonical        = match ( $seo['policy'] ?? 'source' ) {
				'feed' => $item->canonicalUrl ? $item->canonicalUrl : $item->originalUrl, 'local' => '', default => $item->originalUrl };
		if ( ! in_array( 'seo', $protected_fields, true ) ) {
			update_post_meta( $post_id, '_wpfetch_canonical', esc_url_raw( (string) ( $seo['canonical'] ?? $canonical ) ) );
			update_post_meta( $post_id, '_wpfetch_robots', sanitize_text_field( (string) ( $seo['robots'] ?? 'noindex,follow' ) ) );
		}
		if ( ! in_array( 'taxonomies', $protected_fields, true ) && ! empty( $item->categories ) && is_object_in_taxonomy( $source->postType, 'category' ) && 'create' === ( $config['unknown_category_policy'] ?? 'ignore' ) ) {
			wp_set_object_terms( $post_id, array_map( 'sanitize_text_field', $item->categories ), 'category', true ); }
		$media_error = null;
		$image_url   = '';
		foreach ( $item->media as $candidate ) {
			if ( ! empty( $candidate['url'] ) && ( empty( $candidate['type'] ) || str_starts_with( (string) $candidate['type'], 'image' ) ) ) {
				$image_url = (string) $candidate['url'];
				break; }
		}
		if ( ! in_array( 'featured_image', $protected_fields, true ) && ! empty( $config['download_media'] ) && $image_url ) {
			$attachment = $this->media->sideload( $image_url, $post_id, $item->title );
			if ( is_wp_error( $attachment ) ) {
				$state       = 'partially_imported';
				$media_error = $attachment->get_error_message();
			} else {
				set_post_thumbnail( $post_id, $attachment ); }
		}
		$wpdb->update(
			$table,
			array(
				'post_id'            => $post_id,
				'content_hash'       => $content_hash,
				'state'              => $state,
				'first_imported_at'  => $existing['first_imported_at'] ?? $now,
				'last_synced_at'     => $now,
				'source_modified_at' => $item->updatedAt?->format( 'Y-m-d H:i:s' ),
				'error'              => $media_error,
				'updated_at'         => $now,
			),
			array( 'id' => $record_id )
		);
		$this->logger->log( 'info', 'item_' . $state, sprintf( 'Item %s as post %d.', $state, $post_id ), array( 'guid_hash' => $guid_hash ), $source->id );
		do_action( 'wpfetch_post_imported', $post_id, $item, $source );
		return array(
			'state'   => $state,
			'post_id' => $post_id,
			'reason'  => $media_error ? $media_error : '',
		);
	}
}
