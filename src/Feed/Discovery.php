<?php
declare(strict_types=1);

namespace WordPressFetch\Feed;

use WordPressFetch\Security\UrlGuard;

final class Discovery {
	public function __construct( private readonly HttpClient $http, private readonly UrlGuard $guard ) {}

	/** @return list<array{title:string,type:string,url:string}>|\WP_Error */
	public function discover( string $website_url ): array|\WP_Error {
		$response = $this->http->fetch( $website_url );
		if ( is_wp_error( $response ) ) {
			return $response; }
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$ok       = $dom->loadHTML( $response['body'], LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $ok ) {
			return new \WP_Error( 'wpfetch_discovery_html', __( 'The website HTML could not be inspected.', 'wordpress-fetch' ) ); }
		$xp    = new \DOMXPath( $dom );
		$feeds = array();
		foreach ( $xp->query( '//link[contains(concat(" ", normalize-space(@rel), " "), " alternate ")]' ) as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			} $type = strtolower( $link->getAttribute( 'type' ) );
			if ( ! in_array( $type, array( 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', 'text/xml' ), true ) ) {
				continue; }
			$url = $this->absolute( $website_url, $link->getAttribute( 'href' ) );
			if ( $this->guard->validate( $url ) ) {
				$feeds[] = array(
					'title' => $link->getAttribute( 'title' ) ? $link->getAttribute( 'title' ) : __( 'Discovered feed', 'wordpress-fetch' ),
					'type'  => $type,
					'url'   => $url,
				); }
		}
		return array_values( array_unique( $feeds, SORT_REGULAR ) );
	}

	private function absolute( string $base, string $href ): string {
		if ( wp_http_validate_url( $href ) ) {
			return $href;
		} $p = wp_parse_url( $base );
		if ( ! is_array( $p ) || empty( $p['host'] ) ) {
			return ''; }
		if ( str_starts_with( $href, '//' ) ) {
			return ( $p['scheme'] ?? 'https' ) . ':' . $href; }
		$origin = ( $p['scheme'] ?? 'https' ) . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		if ( str_starts_with( $href, '/' ) ) {
			return $origin . $href; }
		$path = isset( $p['path'] ) ? dirname( $p['path'] ) : '';
		return $origin . rtrim( $path, '/' ) . '/' . ltrim( $href, '/' );
	}
}
