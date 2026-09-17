<?php
declare(strict_types=1);

namespace WordPressFetch\Queue;

use WordPressFetch\Infrastructure\Database\Schema;

final class Queue {
	/** @param array<string,mixed> $payload */
	public function enqueue( string $type, array $payload, ?int $source_id = null, int $priority = 10, ?int $available_at = null ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Schema::table( 'jobs' ),
			array(
				'source_id'    => $source_id,
				'type'         => sanitize_key( $type ),
				'status'       => 'pending',
				'payload'      => wp_json_encode( $payload ),
				'priority'     => $priority,
				'attempts'     => 0,
				'max_attempts' => 5,
				'available_at' => gmdate( 'Y-m-d H:i:s', $available_at ?? time() ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/** @return array<string,mixed>|null */
	public function claim( string $worker ): ?array {
		global $wpdb;
		$table   = Schema::table( 'jobs' );
		$token   = wp_generate_uuid4();
		$now     = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + 300 );
		$wpdb->query( 'START TRANSACTION' );
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE ((status IN ('pending','retrying') AND available_at <= %s) OR (status='processing' AND lock_expires_at < %s)) ORDER BY priority ASC, id ASC LIMIT 1 FOR UPDATE", $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $id ) {
			$wpdb->query( 'COMMIT' );
			return null; }
		$wpdb->update(
			$table,
			array(
				'status'          => 'processing',
				'lock_token'      => $token,
				'locked_at'       => $now,
				'lock_expires_at' => $expires,
				'worker'          => sanitize_text_field( $worker ),
				'started_at'      => $now,
				'updated_at'      => $now,
			),
			array( 'id' => (int) $id )
		);
		$wpdb->query( 'COMMIT' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND lock_token=%s", (int) $id, $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/** @param array<string,mixed> $result */
	public function complete( int $id, string $token, array $result ): void {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'jobs' ),
			array(
				'status'          => 'completed',
				'result'          => wp_json_encode( $result ),
				'completed_at'    => current_time( 'mysql', true ),
				'lock_token'      => null,
				'lock_expires_at' => null,
				'updated_at'      => current_time( 'mysql', true ),
			),
			array(
				'id'         => $id,
				'lock_token' => $token,
			)
		); }

	public function fail( int $id, string $token, string $message, bool $retryable = true, ?int $retry_after = null ): void {
		global $wpdb;
		$table = Schema::table( 'jobs' );
		$job   = $wpdb->get_row( $wpdb->prepare( "SELECT attempts,max_attempts FROM {$table} WHERE id=%d AND lock_token=%s", $id, $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $job ) {
			return;
		} $attempts = (int) $job['attempts'] + 1;
		$retry      = $retryable && $attempts < (int) $job['max_attempts'];
		$delay      = $retry_after ?? min( 21600, ( 2 ** $attempts ) * 60 + wp_rand( 0, 30 ) );
		$wpdb->update(
			$table,
			array(
				'status'          => $retry ? 'retrying' : 'failed',
				'attempts'        => $attempts,
				'available_at'    => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'result'          => wp_json_encode( array( 'error' => sanitize_textarea_field( $message ) ) ),
				'lock_token'      => null,
				'lock_expires_at' => null,
				'updated_at'      => current_time( 'mysql', true ),
			),
			array(
				'id'         => $id,
				'lock_token' => $token,
			)
		);
	}

	public function cancel( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			Schema::table( 'jobs' ),
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $id,
				'status' => 'pending',
			)
		); }
	public function retry( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			Schema::table( 'jobs' ),
			array(
				'status'       => 'pending',
				'attempts'     => 0,
				'available_at' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array(
				'id'     => $id,
				'status' => 'failed',
			)
		); }
	/** @return array<string,int> */ public function counts(): array {
		global $wpdb;
		$table = Schema::table( 'jobs' );
		$rows  = $wpdb->get_results( "SELECT status,COUNT(*) total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table with no values.
		$out   = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['total'];
		} return $out; }
}
