<?php
/**
 * Plugin Name:       WordPress Fetch
 * Plugin URI:        https://example.com/wordpress-fetch
 * Description:       Secure feed syndication, transformation, editorial, SEO, and monitoring for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            WordPress Fetch Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordpress-fetch
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFETCH_VERSION', '1.0.0' );
define( 'WPFETCH_SCHEMA_VERSION', '1.0.0' );
define( 'WPFETCH_FILE', __FILE__ );
define( 'WPFETCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFETCH_URL', plugin_dir_url( __FILE__ ) );

$wpfetch_autoload = WPFETCH_DIR . 'vendor/autoload.php';
if ( is_readable( $wpfetch_autoload ) ) {
	require $wpfetch_autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'WordPressFetch\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$file = WPFETCH_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( WordPressFetch\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( WordPressFetch\Core\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		WordPressFetch\Core\Plugin::instance()->boot();
	}
);
