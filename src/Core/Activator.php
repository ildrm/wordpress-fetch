<?php
declare(strict_types=1);

namespace WordPressFetch\Core;

use WordPressFetch\Infrastructure\Database\Schema;
use WordPressFetch\Scheduling\Scheduler;

final class Activator {
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}
		self::install_site();
	}

	private static function install_site(): void {
		Schema::migrate();
		Capabilities::install();
		add_option( 'wpfetch_settings', self::defaults(), '', false );
		add_option( 'wpfetch_show_setup', 1, '', false );
		Scheduler::schedule();
		if ( ! wp_next_scheduled( 'wpfetch_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpfetch_daily_maintenance' );
		}
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'default_status'     => 'draft',
			'default_post_type'  => 'post',
			'default_author'     => 1,
			'seo_policy'         => 'source',
			'robots'             => 'noindex,follow',
			'queue_batch_size'   => 10,
			'max_feed_bytes'     => 5242880,
			'max_items_fetch'    => 500,
			'max_imports_run'    => 100,
			'max_media_bytes'    => 10485760,
			'request_timeout'    => 15,
			'log_retention_days' => 30,
			'uninstall_policy'   => 'preserve',
			'scheduler_mode'     => 'wp_cron',
		);
	}

	public static function deactivate(): void {
		Scheduler::unschedule();
		wp_clear_scheduled_hook( 'wpfetch_daily_maintenance' );
		delete_transient( 'wpfetch_worker_lock' );
	}
}
