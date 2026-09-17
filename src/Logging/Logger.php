<?php
declare(strict_types=1);

namespace WordPressFetch\Logging;

use WordPressFetch\Infrastructure\Database\Schema;

final class Logger {
	/** @param array<string,mixed> $context */
	public function log( string $severity, string $event, string $message, array $context = array(), ?int $source_id = null, ?int $job_id = null ): void {
		global $wpdb;
		$allowed = array( 'debug', 'info', 'warning', 'error', 'critical' );
		if ( ! in_array( $severity, $allowed, true ) ) {
			$severity = 'info'; }
		$redacted = $this->redact( $context );
		$wpdb->insert(
			Schema::table( 'logs' ),
			array(
				'source_id'  => $source_id,
				'job_id'     => $job_id,
				'severity'   => $severity,
				'event'      => sanitize_key( $event ),
				'message'    => sanitize_textarea_field( $message ),
				'context'    => wp_json_encode( $redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $data Context.
	 * @return array<string,mixed>
	 */
	public function redact( array $data ): array {
		foreach ( $data as $key => &$value ) {
			if ( preg_match( '/authorization|password|secret|token|api.?key|cookie/i', (string) $key ) ) {
				$value = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$value = $this->redact( $value );
			} elseif ( is_string( $value ) ) {
				$value = preg_replace( '/(Bearer|Basic)\s+[A-Za-z0-9+\/_=.-]+/i', '$1 [REDACTED]', $value );
			}
		} unset( $value );
		return $data;
	}

	public function prune(): int {
		global $wpdb;
		$settings   = wp_parse_args( get_option( 'wpfetch_settings', array() ), \WordPressFetch\Core\Activator::defaults() );
		$days       = max( 1, min( 365, (int) $settings['log_retention_days'] ) );
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days );
		$deleted    = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'logs' ) . ' WHERE created_at < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$job_cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * max( 30, $days ) );
		$deleted   += (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'jobs' ) . " WHERE status IN ('completed','cancelled') AND updated_at < %s", $job_cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $deleted;
	}
}
