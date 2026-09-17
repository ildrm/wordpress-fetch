<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

final class UrlNormalizer {
	public function normalize( string $url ): string {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return ''; }
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		$host   = strtolower( rtrim( (string) $parts['host'], '.' ) );
		$path   = $parts['path'] ?? '/';
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' ); }
		$query = array();
		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			foreach ( array_keys( $query ) as $key ) {
				if ( str_starts_with( strtolower( (string) $key ), 'utm_' ) || in_array( strtolower( (string) $key ), array( 'fbclid', 'gclid' ), true ) ) {
					unset( $query[ $key ] );
				}
			} ksort( $query ); }
		return $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}
}
