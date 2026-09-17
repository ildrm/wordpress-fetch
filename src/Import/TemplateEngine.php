<?php
declare(strict_types=1);

namespace WordPressFetch\Import;

final class TemplateEngine {
	public const TOKENS = array( 'title', 'content', 'summary', 'description', 'author', 'authors', 'original_url', 'canonical_url', 'source_name', 'source_url', 'published_at', 'updated_at', 'categories', 'tags', 'featured_image_url' );

	/** @param array<string,mixed> $context */
	public function render( string $template, array $context ): string {
		return preg_replace_callback(
			'/{{\s*([a-zA-Z0-9_.-]+)\s*}}/',
			static function ( array $token_match ) use ( $context ): string {
				$value = $context[ $token_match[1] ] ?? '';
				return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			},
			$template
		) ?? '';
	}

	/** @return list<string> */
	public function unknownTokens( string $template ): array {
		preg_match_all( '/{{\s*([a-zA-Z0-9_.-]+)\s*}}/', $template, $matches );
		return array_values( array_diff( array_unique( $matches[1] ), self::TOKENS ) );
	}
}
