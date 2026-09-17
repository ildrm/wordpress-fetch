<?php
declare(strict_types=1);

namespace WordPressFetch\Core;

final class Capabilities {
	public const MANAGE_SOURCES     = 'wpfetch_manage_sources';
	public const EDIT_SOURCES       = 'wpfetch_edit_sources';
	public const DELETE_SOURCES     = 'wpfetch_delete_sources';
	public const RUN_IMPORTS        = 'wpfetch_run_imports';
	public const VIEW_LOGS          = 'wpfetch_view_logs';
	public const MANAGE_SEO         = 'wpfetch_manage_seo';
	public const MANAGE_CREDENTIALS = 'wpfetch_manage_credentials';
	public const MANAGE_SETTINGS    = 'wpfetch_manage_settings';

	/** @return list<string> */
	public static function all(): array {
		return array( self::MANAGE_SOURCES, self::EDIT_SOURCES, self::DELETE_SOURCES, self::RUN_IMPORTS, self::VIEW_LOGS, self::MANAGE_SEO, self::MANAGE_CREDENTIALS, self::MANAGE_SETTINGS );
	}

	public static function install(): void {
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( self::all() as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	public static function remove(): void {
		foreach ( wp_roles()->roles as $role_name => $details ) {
			$role = get_role( (string) $role_name );
			if ( $role ) {
				foreach ( self::all() as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
