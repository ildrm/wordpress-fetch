<?php
declare(strict_types=1);

namespace WordPressFetch\Seo;

final class Manager {
	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'preventDuplicateNativeCanonical' ) );
		add_action( 'wp_head', array( $this, 'renderNative' ), 1 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemapArgs' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( $this, 'canonicalFilter' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonicalFilter' ) );
		add_filter( 'seopress_titles_canonical', array( $this, 'canonicalFilter' ) );
		add_filter( 'aioseo_canonical_url', array( $this, 'canonicalFilter' ) );
	}

	public function preventDuplicateNativeCanonical(): void {
		if ( 'WordPress Fetch Native' === $this->provider() && is_singular() && get_post_meta( get_queried_object_id(), '_wpfetch_source_id', true ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}

	public function provider(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO'; }
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math'; }
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO'; }
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress'; }
		return 'WordPress Fetch Native';
	}

	public function canonicalFilter( string|false $canonical ): string|false {
		$value = $this->canonical();
		return $value ? $value : $canonical; }
	private function canonical(): string {
		if ( ! is_singular() ) {
			return '';
		} return esc_url_raw( (string) get_post_meta( get_queried_object_id(), '_wpfetch_canonical', true ) ); }

	public function renderNative(): void {
		if ( 'WordPress Fetch Native' !== $this->provider() || ! is_singular() || ! get_post_meta( get_queried_object_id(), '_wpfetch_source_id', true ) ) {
			return; }
		$canonical = $this->canonical();
		if ( $canonical ) {
			printf( "\n<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) ); }
		$title       = get_the_title( get_queried_object_id() );
		$description = wp_strip_all_tags( get_the_excerpt( get_queried_object_id() ) );
		$image       = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( $description ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		} if ( $image ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) ); }
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => $title,
			'datePublished' => get_the_date( DATE_W3C, get_queried_object_id() ),
			'dateModified'  => get_the_modified_date( DATE_W3C, get_queried_object_id() ),
		);
		if ( $image ) {
			$schema['image'] = $image;
		} echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string,bool|string> $robots Robots directives.
	 * @return array<string,bool|string>
	 */
	public function robots( array $robots ): array {
		if ( ! is_singular() ) {
			return $robots;
		} $value = (string) get_post_meta( get_queried_object_id(), '_wpfetch_robots', true );
		if ( ! $value ) {
			return $robots;
		} foreach ( array_map( 'trim', explode( ',', $value ) ) as $directive ) {
			if ( str_starts_with( $directive, 'no' ) ) {
				unset( $robots[ substr( $directive, 2 ) ] );
				$robots[ $directive ] = true;
			} else {
				$robots[ $directive ] = true;
			}
		} return $robots; }

	/**
	 * @param array<string,mixed> $args Sitemap arguments.
	 * @return array<string,mixed>
	 */
	public function sitemapArgs( array $args, string $post_type ): array {
		$args['meta_query'] = array_merge(
			(array) ( $args['meta_query'] ?? array() ),
			array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_wpfetch_robots',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wpfetch_robots',
						'value'   => 'noindex',
						'compare' => 'NOT LIKE',
					),
				),
			)
		);
		return $args; }
}
