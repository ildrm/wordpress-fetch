<?php
declare(strict_types=1);

namespace WordPressFetch\Infrastructure\Database;

final class Schema {
	public static function table( string $name ): string {
		global $wpdb;
		$allowed = array( 'sources', 'groups', 'profiles', 'jobs', 'imports', 'logs' );
		if ( ! in_array( $name, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'Unknown WordPress Fetch table.' );
		}
		return $wpdb->prefix . 'wpfetch_' . $name;
	}

	public static function migrate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$sources  = self::table( 'sources' );
		$groups   = self::table( 'groups' );
		$profiles = self::table( 'profiles' );
		$jobs     = self::table( 'jobs' );
		$imports  = self::table( 'imports' );
		$logs     = self::table( 'logs' );

		dbDelta(
			"CREATE TABLE {$sources} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL, name varchar(191) NOT NULL, website_url text NULL, feed_url text NOT NULL,
			source_type varchar(20) NOT NULL DEFAULT 'auto', detected_type varchar(20) NULL, group_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'disabled', language varchar(20) NULL, timezone varchar(64) NULL,
			post_type varchar(64) NOT NULL DEFAULT 'post', post_status varchar(20) NOT NULL DEFAULT 'draft', author_id bigint(20) unsigned NOT NULL DEFAULT 1,
			schedule varchar(32) NOT NULL DEFAULT 'hourly', next_run datetime NULL, last_fetch datetime NULL, last_success datetime NULL,
			health varchar(32) NOT NULL DEFAULT 'unknown', failure_count int unsigned NOT NULL DEFAULT 0,
			etag varchar(191) NULL, last_modified varchar(191) NULL, config longtext NOT NULL, secret longtext NULL,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY uuid (uuid), KEY status_next (status,next_run), KEY group_id (group_id), KEY health (health)
		) {$charset};"
		);

		dbDelta( "CREATE TABLE {$groups} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(191) NOT NULL, parent_id bigint(20) unsigned NULL, config longtext NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY parent_id (parent_id)) {$charset};" );
		dbDelta( "CREATE TABLE {$profiles} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(191) NOT NULL, type varchar(32) NOT NULL, config longtext NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY type (type)) {$charset};" );
		dbDelta(
			"CREATE TABLE {$jobs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, source_id bigint(20) unsigned NULL, type varchar(32) NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending',
			payload longtext NOT NULL, result longtext NULL, priority smallint NOT NULL DEFAULT 10, attempts smallint unsigned NOT NULL DEFAULT 0, max_attempts smallint unsigned NOT NULL DEFAULT 5,
			available_at datetime NOT NULL, locked_at datetime NULL, lock_expires_at datetime NULL, lock_token char(36) NULL, worker varchar(191) NULL,
			started_at datetime NULL, completed_at datetime NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY (id), KEY claim (status,available_at,priority), KEY source_status (source_id,status), KEY lock_expires (lock_expires_at)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$imports} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, source_id bigint(20) unsigned NOT NULL, post_id bigint(20) unsigned NULL,
			guid_hash char(64) NOT NULL, url_hash char(64) NOT NULL, content_hash char(64) NOT NULL, original_guid text NULL, original_url text NULL, normalized_url text NULL,
			state varchar(32) NOT NULL, protected_fields text NULL, first_imported_at datetime NULL, last_synced_at datetime NULL, source_modified_at datetime NULL, error text NULL,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY source_guid (source_id,guid_hash), UNIQUE KEY url_hash (url_hash), KEY post_id (post_id), KEY source_state (source_id,state), KEY content_hash (content_hash)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, source_id bigint(20) unsigned NULL, job_id bigint(20) unsigned NULL, severity varchar(16) NOT NULL, event varchar(64) NOT NULL,
			message text NOT NULL, context longtext NULL, created_at datetime NOT NULL,
			PRIMARY KEY (id), KEY source_created (source_id,created_at), KEY severity_created (severity,created_at), KEY job_id (job_id)
		) {$charset};"
		);
		update_option( 'wpfetch_schema_version', WPFETCH_SCHEMA_VERSION, false );
	}
}
