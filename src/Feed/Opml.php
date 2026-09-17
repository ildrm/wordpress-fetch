<?php
declare(strict_types=1);

namespace WordPressFetch\Feed;

use WordPressFetch\Security\UrlGuard;

final class Opml {
	public function __construct( private readonly UrlGuard $guard ) {}

	/** @return list<array{title:string,feed_url:string,website_url:string,group:string,valid:bool}>|\WP_Error */
	public function parse( string $xml ): array|\WP_Error {
		if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $xml ) ) {
			return new \WP_Error( 'wpfetch_unsafe_opml', __( 'Unsafe OPML declarations were rejected.', 'wordpress-fetch' ) ); }
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$ok       = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $ok || ! $dom->documentElement || 'opml' !== strtolower( $dom->documentElement->localName ) ) {
			return new \WP_Error( 'wpfetch_invalid_opml', __( 'This is not valid OPML.', 'wordpress-fetch' ) ); }
		$out = array();
		$this->walk( $dom->getElementsByTagName( 'body' )->item( 0 ), array(), $out );
		$seen = array();
		return array_values(
			array_filter(
				$out,
				static function ( array $feed ) use ( &$seen ): bool {
					$key = strtolower( rtrim( $feed['feed_url'], '/' ) );
					if ( isset( $seen[ $key ] ) ) {
						return false;
					} $seen[ $key ] = true;
					return true;
				}
			)
		);
	}

	/**
	 * @param list<string> $groups Group path.
	 * @param list<array{title:string,feed_url:string,website_url:string,group:string,valid:bool}> $out Result accumulator.
	 */
	private function walk( ?\DOMNode $node, array $groups, array &$out ): void {
		if ( ! $node ) {
			return;
		} foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement || 'outline' !== strtolower( $child->localName ) ) {
				continue;
			} $title = sanitize_text_field( $child->getAttribute( 'text' ) ? $child->getAttribute( 'text' ) : $child->getAttribute( 'title' ) );
			$feed    = esc_url_raw( $child->getAttribute( 'xmlUrl' ) );
			if ( $feed ) {
				$host  = wp_parse_url( $feed, PHP_URL_HOST );
				$out[] = array(
					'title'       => $title ? $title : ( is_string( $host ) ? $host : $feed ),
					'feed_url'    => $feed,
					'website_url' => esc_url_raw( $child->getAttribute( 'htmlUrl' ) ),
					'group'       => implode( ' / ', $groups ),
					'valid'       => $this->guard->validate( $feed ),
				);
			} else {
				$this->walk( $child, array_merge( $groups, $title ? array( $title ) : array() ), $out ); }
		}
	}

	/** @param list<array<string,mixed>> $sources */
	public function export( array $sources ): string {
		$dom               = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;
		$opml              = $dom->appendChild( $dom->createElement( 'opml' ) );
		$opml->setAttribute( 'version', '2.0' );
		$head = $opml->appendChild( $dom->createElement( 'head' ) );
		$head->appendChild( $dom->createElement( 'title', 'WordPress Fetch Sources' ) );
		$body = $opml->appendChild( $dom->createElement( 'body' ) );
		foreach ( $sources as $source ) {
			$outline = $body->appendChild( $dom->createElement( 'outline' ) );
			$outline->setAttribute( 'text', (string) ( $source['name'] ?? '' ) );
			$outline->setAttribute( 'type', 'rss' );
			$outline->setAttribute( 'xmlUrl', (string) ( $source['feed_url'] ?? '' ) );
			if ( ! empty( $source['website_url'] ) ) {
				$outline->setAttribute( 'htmlUrl', (string) $source['website_url'] );
			}
		} $xml = $dom->saveXML();
		return $xml ? $xml : '';
	}
}
