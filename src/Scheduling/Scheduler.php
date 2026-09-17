<?php
declare(strict_types=1);

namespace WordPressFetch\Scheduling;

use WordPressFetch\Infrastructure\Database\Schema;
use WordPressFetch\Queue\Queue;

final class Scheduler {
	public const HOOK = 'wpfetch_scheduler_tick';
	/**
	 * @param array<string,array{interval:int,display:string}> $schedules Schedules.
	 * @return array<string,array{interval:int,display:string}>
	 */
	public static function intervals( array $schedules ): array {
		$schedules['wpfetch_5min']  = array(
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'wordpress-fetch' ),
		);
		$schedules['wpfetch_15min'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'wordpress-fetch' ),
		);
		$schedules['wpfetch_30min'] = array(
			'interval' => 1800,
			'display'  => __( 'Every 30 minutes', 'wordpress-fetch' ),
		);
		$schedules['weekly']        = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Weekly', 'wordpress-fetch' ),
		);
		return $schedules; }
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'wpfetch_5min', self::HOOK ); } }
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK ); }
	public function __construct( private readonly Queue $queue ) {}
	public function tick(): void {
		global $wpdb;
		$table = Schema::table( 'sources' );
		$now   = current_time( 'mysql', true );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id,schedule FROM {$table} WHERE status='enabled' AND schedule <> 'manual' AND (next_run IS NULL OR next_run <= %s) ORDER BY id LIMIT 100", $now ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted internal table.
		foreach ( $rows ? $rows : array() as $row ) {
			$this->queue->enqueue( 'fetch', array(), (int) $row['id'] );
			$seconds = $this->seconds( (string) $row['schedule'] );
			$wpdb->update(
				$table,
				array(
					'next_run'   => gmdate( 'Y-m-d H:i:s', time() + $seconds ),
					'updated_at' => $now,
				),
				array( 'id' => (int) $row['id'] )
			);
		} do_action( 'wpfetch_run_worker' ); }
	private function seconds( string $schedule ): int {
		return match ( $schedule ) {
			'wpfetch_5min' => 300, 'wpfetch_15min' => 900, 'wpfetch_30min' => 1800, 'twicedaily' => 43200, 'daily' => DAY_IN_SECONDS, 'weekly' => WEEK_IN_SECONDS, default => HOUR_IN_SECONDS }; }
}
