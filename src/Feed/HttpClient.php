<?php
declare(strict_types=1);

namespace WordPressFetch\Feed;

use WordPressFetch\Domain\Source;
use WordPressFetch\Security\UrlGuard;

final class HttpClient {
	public function __construct( private readonly UrlGuard $guard ) {}

	/** @return array{status:int,body:string,headers:array<string,string>,duration_ms:int,url:string}|\WP_Error */
	public function fetch( string $url, ?Source $source = null, string $method = 'GET' ): array|\WP_Error {
		if ( ! $this->guard->validate( $url ) ) {
			return new \WP_Error( 'wpfetch_unsafe_url', __( 'The URL resolves to a blocked or private network destination.', 'wordpress-fetch' ), array( 'status' => 400 ) ); }
		$settings = wp_parse_args( get_option( 'wpfetch_settings', array() ), \WordPressFetch\Core\Activator::defaults() );
		$headers  = array(
			'Accept'     => 'application/rss+xml, application/atom+xml, application/rdf+xml, application/xml, text/xml, text/html;q=0.8',
			'User-Agent' => 'WordPress-Fetch/' . WPFETCH_VERSION . '; ' . home_url( '/' ),
		);
		if ( $source?->etag ) {
			$headers['If-None-Match'] = $source->etag; }
		if ( $source?->lastModified ) {
			$headers['If-Modified-Since'] = $source->lastModified; }
		if ( $source ) {
			$auth = (string) ( $source->credentials['type'] ?? 'none' );
			if ( 'basic' === $auth && isset( $source->credentials['username'], $source->credentials['password'] ) ) {
				$headers['Authorization'] = 'Basic ' . base64_encode( (string) $source->credentials['username'] . ':' . (string) $source->credentials['password'] ); }
			if ( 'bearer' === $auth && ! empty( $source->credentials['token'] ) ) {
				$headers['Authorization'] = 'Bearer ' . (string) $source->credentials['token']; }
			if ( 'header' === $auth && ! empty( $source->credentials['header_name'] ) ) {
				$name = preg_replace( '/[^A-Za-z0-9-]/', '', (string) $source->credentials['header_name'] );
				if ( $name ) {
					$headers[ $name ] = (string) ( $source->credentials['header_value'] ?? '' ); }
			}
			foreach ( (array) ( $source->credentials['headers'] ?? array() ) as $name => $value ) {
				$name = preg_replace( '/[^A-Za-z0-9-]/', '', (string) $name );
				if ( $name && ! in_array( strtolower( $name ), array( 'host', 'content-length', 'connection' ), true ) ) {
					$headers[ $name ] = (string) $value; }
			}
		}
		/** @var array<string,string> $headers */
		$headers  = apply_filters( 'wpfetch_request_headers', $headers, $url, $source );
		$start    = microtime( true );
		$response = wp_safe_remote_request(
			$url,
			array(
				'method'              => $method,
				'timeout'             => min( 30, max( 3, (int) $settings['request_timeout'] ) ),
				'redirection'         => 5,
				'limit_response_size' => (int) $settings['max_feed_bytes'] + 1,
				'headers'             => $headers,
				'reject_unsafe_urls'  => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->humanize( $response ); }
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > (int) $settings['max_feed_bytes'] ) {
			return new \WP_Error( 'wpfetch_oversized', __( 'The response exceeds the configured maximum feed size.', 'wordpress-fetch' ), array( 'status' => 413 ) ); }
		$status = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status, array( 200, 304 ), true ) ) {
			return $this->statusError( $status, wp_remote_retrieve_header( $response, 'retry-after' ) ); }
		$out_headers = array();
		foreach ( wp_remote_retrieve_headers( $response ) as $key => $value ) {
			$out_headers[ strtolower( (string) $key ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value; }
		return array(
			'status'      => $status,
			'body'        => $body,
			'headers'     => $out_headers,
			'duration_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
			'url'         => $url,
		);
	}

	private function humanize( \WP_Error $error ): \WP_Error {
		return new \WP_Error(
			'wpfetch_connection',
			/* translators: %s: network error message. */
			sprintf( __( 'The source could not be reached: %s', 'wordpress-fetch' ), $error->get_error_message() ),
			array(
				'status'        => 502,
				'original_code' => $error->get_error_code(),
			)
		);
	}

	private function statusError( int $status, string $retry_after ): \WP_Error {
		$messages = array(
			401 => __( 'The source returned HTTP 401. Verify its authentication credentials.', 'wordpress-fetch' ),
			403 => __( 'The publisher refused access (HTTP 403).', 'wordpress-fetch' ),
			404 => __( 'The feed was not found (HTTP 404).', 'wordpress-fetch' ),
			410 => __( 'The publisher reports that this feed is permanently gone (HTTP 410).', 'wordpress-fetch' ),
			429 => __( 'The publisher is rate limiting requests (HTTP 429). WordPress Fetch will back off.', 'wordpress-fetch' ),
		);
		return new \WP_Error(
			'wpfetch_http_' . $status,
			/* translators: %d: HTTP status code. */
			$messages[ $status ] ?? sprintf( __( 'The source returned HTTP %d.', 'wordpress-fetch' ), $status ),
			array(
				'status'          => 502,
				'upstream_status' => $status,
				'retry_after'     => $retry_after,
			)
		);
	}
}
