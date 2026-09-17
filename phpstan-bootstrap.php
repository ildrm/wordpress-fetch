<?php
declare(strict_types=1);

define( 'WPFETCH_DIR', __DIR__ . '/' );
define( 'WPFETCH_URL', 'https://example.test/wp-content/plugins/wordpress-fetch/' );
define( 'WPFETCH_FILE', __DIR__ . '/wordpress-fetch.php' );
define( 'WPFETCH_VERSION', '1.0.0' );
define( 'WPFETCH_SCHEMA_VERSION', '1.0.0' );

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static function add_command( string $name, mixed $callable ): void {}
		public static function line( string $message ): void {}
		public static function success( string $message ): void {}
		public static function error( string $message ): never { throw new RuntimeException( $message ); }
	}
}
