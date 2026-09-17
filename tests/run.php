<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use WordPressFetch\Feed\Parser;
use WordPressFetch\Import\RuleEngine;
use WordPressFetch\Import\TemplateEngine;
use WordPressFetch\Import\UrlNormalizer;
use WordPressFetch\Security\HtmlSanitizer;
use WordPressFetch\Security\UrlGuard;

$passed = 0; $failed = 0;
function check( bool $condition, string $label ): void { global $passed, $failed; if ( $condition ) { ++$passed; echo "PASS {$label}\n"; } else { ++$failed; echo "FAIL {$label}\n"; } }
$parser = new Parser( new HtmlSanitizer() );
foreach ( array( 'rss-basic.xml' => 'rss', 'rss-content.xml' => 'rss', 'rdf.xml' => 'rdf', 'atom.xml' => 'atom', 'media-rss.xml' => 'rss', 'podcast.xml' => 'rss', 'persian.xml' => 'rss' ) as $fixture => $type ) { $result = $parser->parse( file_get_contents( __DIR__ . '/Fixtures/' . $fixture ) ); check( is_array( $result ) && $result['type'] === $type && count( $result['items'] ) > 0, "parse {$fixture}" ); }
$unsafe = $parser->parse( file_get_contents( __DIR__ . '/Fixtures/xxe.xml' ) ); check( $unsafe instanceof WP_Error || ( is_object( $unsafe ) && method_exists( $unsafe, 'get_error_code' ) ), 'reject XXE declarations' );
$malformed = $parser->parse( '<rss><channel><item>' ); check( is_object( $malformed ), 'reject malformed XML' );
$rss = $parser->parse( file_get_contents( __DIR__ . '/Fixtures/rss-content.xml' ) ); check( is_array( $rss ) && ! str_contains( $rss['items'][0]->content, '<script' ) && ! str_contains( $rss['items'][0]->content, 'onclick=' ), 'sanitize hostile HTML' );
$normalizer = new UrlNormalizer(); check( 'https://example.com/article?a=1' === $normalizer->normalize( 'https://EXAMPLE.com/article/?utm_source=x&a=1#section' ), 'normalize tracking URL' );
$templates = new TemplateEngine(); check( 'Hello World' === $templates->render( 'Hello {{ title }}', array( 'title' => 'World' ) ), 'render template' ); check( array( 'bogus' ) === $templates->unknownTokens( '{{title}} {{bogus}}' ), 'validate template tokens' );
$rules = new RuleEngine(); $group = array( 'mode' => 'ALL', 'conditions' => array( array( 'field' => 'title', 'operator' => 'contains', 'value' => 'WordPress' ), array( 'mode' => 'ANY', 'conditions' => array( array( 'field' => 'category', 'operator' => 'equals', 'value' => 'Tech' ), array( 'field' => 'author', 'operator' => 'equals', 'value' => 'Sara' ) ) ) ) ); check( $rules->matches( $group, array( 'title' => 'WordPress release', 'category' => 'News', 'author' => 'Sara' ) ), 'nested rule groups' ); check( ! $rules->matches( $group, array( 'title' => 'Other', 'category' => 'Tech', 'author' => 'Sara' ) ), 'rule rejection' );
$guard = new UrlGuard();
foreach ( array( 'http://127.0.0.1/feed', 'http://localhost/feed', 'http://10.0.0.1/feed', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/feed', 'file:///etc/passwd' ) as $unsafe_url ) { check( ! $guard->validate( $unsafe_url ), 'reject unsafe URL ' . $unsafe_url ); }
check( $guard->validate( 'https://8.8.8.8/feed' ), 'allow public HTTP destination' );
echo "\n{$passed} passed, {$failed} failed\n"; exit( $failed ? 1 : 0 );
