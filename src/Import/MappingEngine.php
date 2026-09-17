<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

use WordPressFetch\Domain\FeedItem;
use WordPressFetch\Domain\Source;

final class MappingEngine {
	public function __construct( private readonly TemplateEngine $templates ) {}

	/** @return array<string,mixed> */
	public function map( Source $source, FeedItem $item ): array {
		$context = $item->jsonSerialize() + array(
			'source_name' => $source->name,
			'source_url'  => $source->feedUrl,
			'description' => $item->summary,
		);
		$config  = $source->config;
		$mode    = (string) ( $config['content_mode'] ?? 'full' );
		$content = match ( $mode ) {
			'summary' => $item->summary, 'title_link' => '<p><a href="' . esc_url( $item->originalUrl ) . '">' . esc_html( $item->title ) . '</a></p>', 'template' => $this->templates->render( (string) ( $config['content_template'] ?? '{{content}}' ), $context ), default => $item->content };
		if ( ! empty( $config['attribution'] ) ) {
			$content .= '<p class="wpfetch-attribution">' . wp_kses_post( $this->templates->render( (string) $config['attribution'], $context ) ) . '</p>'; }
		$post = array(
			'post_type'    => $source->postType,
			'post_status'  => $source->postStatus,
			'post_author'  => $source->authorId,
			'post_title'   => $item->title ? $item->title : __( 'Untitled feed item', 'wordpress-fetch' ),
			'post_content' => $content,
			'post_excerpt' => $item->summary,
		);
		if ( ! empty( $config['use_source_date'] ) && $item->publishedAt ) {
			$post['post_date_gmt'] = $item->publishedAt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] ); }
		foreach ( (array) ( $config['mappings'] ?? array() ) as $mapping ) {
			if ( ! is_array( $mapping ) || empty( $mapping['target'] ) ) {
				continue;
			} $value = $this->templates->render( (string) ( $mapping['template'] ?? '' ), $context );
			$value   = $this->transform( $value, (array) ( $mapping['transforms'] ?? array() ), $mapping );
			if ( str_starts_with( (string) $mapping['target'], 'post_' ) ) {
				$post[ (string) $mapping['target'] ] = $value;
			} elseif ( str_starts_with( (string) $mapping['target'], 'meta:' ) ) {
				$key = sanitize_key( substr( (string) $mapping['target'], 5 ) );
				if ( $key && ! str_starts_with( $key, '_wpfetch_' ) ) {
					$post['meta_input'][ $key ] = $value; }
			}
		}
		return apply_filters( 'wpfetch_mapped_post', $post, $item, $source );
	}

	/**
	 * @param list<string>        $transforms Transform names.
	 * @param array<string,mixed> $mapping Mapping configuration.
	 */
	private function transform( string $value, array $transforms, array $mapping ): string {
		foreach ( $transforms as $transform ) {
			$value = match ( $transform ) {
				'trim' => trim( $value ),
				'normalize_whitespace' => preg_replace( '/\s+/u', ' ', trim( $value ) ) ?? $value,
				'html_to_text' => wp_strip_all_tags( $value ),
				'lowercase' => function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value ),
				'uppercase' => function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value ),
				'prefix' => (string) ( $mapping['prefix'] ?? '' ) . $value,
				'suffix' => $value . (string) ( $mapping['suffix'] ?? '' ),
				default => $value,
			};
		}
		return $value;
	}
}
