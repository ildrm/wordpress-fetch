<?php
/** WordPress Fetch uninstall routine. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }
$wpfetch_policy = get_option( 'wpfetch_settings', array() )['uninstall_policy'] ?? 'preserve';
if ( 'preserve' === $wpfetch_policy ) {
	return; }
global $wpdb;
$wpfetch_tables = array( 'sources', 'groups', 'profiles', 'jobs', 'imports', 'logs' );
if ( 'full_cleanup' === $wpfetch_policy ) {
	$wpfetch_post_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->prefix}wpfetch_imports WHERE post_id IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( $wpfetch_post_ids as $wpfetch_post_id ) {
		wp_delete_post( (int) $wpfetch_post_id, true ); }
}
foreach ( $wpfetch_tables as $wpfetch_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpfetch_{$wpfetch_table}" ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
delete_option( 'wpfetch_settings' );
delete_option( 'wpfetch_schema_version' );
delete_option( 'wpfetch_show_setup' );
foreach ( wp_roles()->roles as $wpfetch_role_name => $wpfetch_details ) {
	$wpfetch_role = get_role( (string) $wpfetch_role_name );
	if ( $wpfetch_role ) {
		foreach ( array( 'wpfetch_manage_sources', 'wpfetch_edit_sources', 'wpfetch_delete_sources', 'wpfetch_run_imports', 'wpfetch_view_logs', 'wpfetch_manage_seo', 'wpfetch_manage_credentials', 'wpfetch_manage_settings' ) as $wpfetch_capability ) {
			$wpfetch_role->remove_cap( $wpfetch_capability ); }
	}
}
