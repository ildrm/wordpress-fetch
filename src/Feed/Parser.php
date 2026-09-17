<?php
declare(strict_types=1);

namespace WordPressFetch\Feed;

use WordPressFetch\Domain\FeedItem;
use WordPressFetch\Security\HtmlSanitizer;

final class Parser {
	public function __construct( private readonly HtmlSanitizer $sanitizer ) {}

	/** @return array{type:string,title:string,description:string,language:string,namespaces:array<string,string>,items:list<FeedItem>}|\WP_Error */
	public function parse( string $xml ): array|\WP_Error {
		if ( '' === trim( $xml ) ) {
			return new \WP_Error( 'wpfetch_empty_feed', __( 'The feed is empty.', 'wordpress-fetch' ) ); }
		if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $xml ) ) {
			return new \WP_Error( 'wpfetch_unsafe_xml', __( 'The XML contains a forbidden document type or entity declaration.', 'wordpress-fetch' ) ); }
		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_NOBLANKS );
		$errors   = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement ) {
			$message = isset( $errors[0] ) ? trim( $errors[0]->message ) : __( 'Malformed XML.', 'wordpress-fetch' );
			/* translators: %s: XML parser error message. */
			return new \WP_Error( 'wpfetch_invalid_xml', sprintf( __( 'The feed XML could not be parsed: %s', 'wordpress-fetch' ), $message ) );
		}
		if ( $this->depth( $dom->documentElement ) > 64 ) {
			return new \WP_Error( 'wpfetch_xml_depth', __( 'The XML nesting depth exceeds the safety limit.', 'wordpress-fetch' ) ); }
		$root = strtolower( $dom->documentElement->localName );
		if ( 'feed' === $root ) {
			return $this->parseAtom( $dom ); }
		if ( 'rss' === $root || 'rdf' === $root ) {
			return $this->parseRss( $dom, 'rdf' === $root ? 'rdf' : 'rss' ); }
		return new \WP_Error( 'wpfetch_unsupported_feed', __( 'The document is not a supported RSS, RDF, or Atom feed.', 'wordpress-fetch' ) );
	}

	/** @return array{type:string,title:string,description:string,language:string,namespaces:array<string,string>,items:list<FeedItem>} */
	private function parseRss( \DOMDocument $dom, string $type ): array {
		$xp = new \DOMXPath( $dom );
		$xp->registerNamespace( 'content', 'http://purl.org/rss/1.0/modules/content/' );
		$xp->registerNamespace( 'dc', 'http://purl.org/dc/elements/1.1/' );
		$xp->registerNamespace( 'media', 'http://search.yahoo.com/mrss/' );
		$channel = $xp->query( '//*[local-name()="channel"]' )->item( 0 );
		$items   = array();
		foreach ( $xp->query( '//*[local-name()="item"]' ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue; }
			$title         = $this->value( $xp, './*[local-name()="title"]', $node );
			$url           = $this->value( $xp, './*[local-name()="link"]', $node );
			$guid_value    = $this->value( $xp, './*[local-name()="guid"]', $node );
			$guid          = $guid_value ? $guid_value : $url;
			$content_value = $this->value( $xp, './content:encoded', $node );
			$content       = $content_value ? $content_value : $this->value( $xp, './*[local-name()="description"]', $node );
			$summary       = $this->value( $xp, './*[local-name()="description"]', $node );
			$authors       = $this->values( $xp, './dc:creator|./*[local-name()="author"]', $node );
			$categories    = $this->values( $xp, './*[local-name()="category"]', $node );
			$media         = $this->rssMedia( $xp, $node, $content );
			$items[]       = new FeedItem( $guid ? $guid : hash( 'sha256', $title . $content ), $url, $title, $this->sanitizer->sanitize( $content ), wp_strip_all_tags( $summary ), $authors, $this->date( $this->value( $xp, './*[local-name()="pubDate"]|./dc:date', $node ) ), null, $categories, array(), $media, null, null, array() );
		}
		return array(
			'type'        => $type,
			'title'       => $channel ? $this->value( $xp, './*[local-name()="title"]', $channel ) : '',
			'description' => $channel ? $this->value( $xp, './*[local-name()="description"]', $channel ) : '',
			'language'    => $channel ? $this->value( $xp, './*[local-name()="language"]', $channel ) : '',
			'namespaces'  => $this->namespaces( $dom ),
			'items'       => $items,
		);
	}

	/** @return array{type:string,title:string,description:string,language:string,namespaces:array<string,string>,items:list<FeedItem>} */
	private function parseAtom( \DOMDocument $dom ): array {
		$xp        = new \DOMXPath( $dom );
		$namespace = $dom->documentElement?->namespaceURI;
		$xp->registerNamespace( 'a', $namespace ? $namespace : 'http://www.w3.org/2005/Atom' );
		$items = array();
		foreach ( $xp->query( '//*[local-name()="entry"]' ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue; }
			$url = '';
			foreach ( $xp->query( './*[local-name()="link"]', $node ) as $link ) {
				if ( $link instanceof \DOMElement && ( ! $link->hasAttribute( 'rel' ) || 'alternate' === $link->getAttribute( 'rel' ) ) ) {
					$url = $link->getAttribute( 'href' );
					break; }
			}
			$authors = array();
			foreach ( $xp->query( './*[local-name()="author"]/*[local-name()="name"]', $node ) as $author ) {
				$authors[] = trim( $author->textContent ); }
			$categories = array();
			foreach ( $xp->query( './*[local-name()="category"]', $node ) as $category ) {
				if ( $category instanceof \DOMElement ) {
					$term         = $category->getAttribute( 'term' );
					$categories[] = $term ? $term : trim( $category->textContent ); }
			}
			$content = $this->value( $xp, './*[local-name()="content"]', $node );
			$summary = $this->value( $xp, './*[local-name()="summary"]', $node );
			$media   = array();
			foreach ( $xp->query( './*[local-name()="link"]', $node ) as $link ) {
				if ( $link instanceof \DOMElement && 'enclosure' === $link->getAttribute( 'rel' ) ) {
					$media[] = array(
						'url'    => $link->getAttribute( 'href' ),
						'type'   => $link->getAttribute( 'type' ),
						'length' => (int) $link->getAttribute( 'length' ),
					); }
			}
			$id       = $this->value( $xp, './*[local-name()="id"]', $node );
			$language = $node->getAttribute( 'xml:lang' );
			$items[]  = new FeedItem( $id ? $id : $url, $url, $this->value( $xp, './*[local-name()="title"]', $node ), $this->sanitizer->sanitize( $content ? $content : $summary ), wp_strip_all_tags( $summary ), $authors, $this->date( $this->value( $xp, './*[local-name()="published"]', $node ) ), $this->date( $this->value( $xp, './*[local-name()="updated"]', $node ) ), $categories, array(), $media, null, $language ? $language : null, array() );
		}
		$root = $dom->documentElement;
		return array(
			'type'        => 'atom',
			'title'       => $root ? $this->value( $xp, './*[local-name()="title"]', $root ) : '',
			'description' => $root ? $this->value( $xp, './*[local-name()="subtitle"]', $root ) : '',
			'language'    => $root && $root->getAttribute( 'xml:lang' ) ? $root->getAttribute( 'xml:lang' ) : '',
			'namespaces'  => $this->namespaces( $dom ),
			'items'       => $items,
		);
	}

	/** @return list<array<string,mixed>> */
	private function rssMedia( \DOMXPath $xp, \DOMElement $node, string $content ): array {
		$out = array();
		foreach ( $xp->query( './media:content|./media:thumbnail|./*[local-name()="enclosure"]', $node ) as $media ) {
			if ( $media instanceof \DOMElement && $media->getAttribute( 'url' ) ) {
				$out[] = array(
					'url'    => $media->getAttribute( 'url' ),
					'type'   => $media->getAttribute( 'type' ),
					'width'  => (int) $media->getAttribute( 'width' ),
					'height' => (int) $media->getAttribute( 'height' ),
				); }
		}
		if ( ! $out && preg_match( '/<img[^>]+src=["\']([^"\']+)/i', $content, $match ) ) {
			$out[] = array(
				'url'  => html_entity_decode( $match[1] ),
				'type' => 'image',
			); }
		return $out;
	}

	private function value( \DOMXPath $xp, string $query, \DOMNode $context ): string {
		$node = $xp->query( $query, $context )->item( 0 );
		return $node ? trim( $node->textContent ) : ''; }
	/** @return list<string> */ private function values( \DOMXPath $xp, string $query, \DOMNode $context ): array {
		$out = array();
		foreach ( $xp->query( $query, $context ) as $node ) {
			$value = trim( $node->textContent );
			if ( '' !== $value ) {
				$out[] = $value;
			}
		} return array_values( array_unique( $out ) ); }
	private function date( string $value ): ?\DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		} try {
			return new \DateTimeImmutable( $value );
		} catch ( \Exception ) {
			return null; } }
	private function depth( \DOMNode $node, int $depth = 0 ): int {
		$max = $depth;
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$max = max( $max, $this->depth( $child, $depth + 1 ) );
				if ( $max > 64 ) {
					break;
				}
			}
		} return $max; }
	/** @return array<string,string> */ private function namespaces( \DOMDocument $dom ): array {
		$out = array();
		if ( $dom->documentElement ) {
			foreach ( $dom->documentElement->attributes as $attr ) {
				if ( str_starts_with( $attr->nodeName, 'xmlns' ) ) {
					$out[ $attr->nodeName ] = $attr->nodeValue;
				}
			}
		} return $out; }
}
