<?php
declare(strict_types=1);

define( 'WPFETCH_VERSION', '1.0.0' );
if ( ! defined( 'DATE_W3C' ) ) { define( 'DATE_W3C', 'Y-m-d\TH:i:sP' ); }
class WP_Error {
	public function __construct( private string $code, private string $message = '', private mixed $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data(): mixed { return $this->data; }
}
function __( string $text, string $domain = '' ): string { return $text; }
function esc_url( string $url ): string { return filter_var( $url, FILTER_SANITIZE_URL ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function wp_strip_all_tags( string $text ): string { return strip_tags( $text ); }
function wp_kses_post( string $html ): string { return preg_replace( '/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', preg_replace( '#<script[^>]*>.*?</script>#is', '', $html ) ) ?? ''; }
function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
function wp_http_validate_url( string $url ): string|false { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false; }

spl_autoload_register( static function ( string $class ): void { $prefix = 'WordPressFetch\\'; if ( str_starts_with( $class, $prefix ) ) { require dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php'; } } );
