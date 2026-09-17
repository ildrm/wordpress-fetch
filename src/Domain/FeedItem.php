<?php
declare(strict_types=1);

namespace WordPressFetch\Domain;

final class FeedItem implements \JsonSerializable {
	/**
	 * @param list<string>              $authors Authors.
	 * @param list<string>              $categories Categories.
	 * @param list<string>              $tags Tags.
	 * @param list<array<string,mixed>> $media Media candidates.
	 * @param array<string,mixed>       $extensions Extension data.
	 */
	public function __construct(
		public readonly string $guid,
		public readonly string $originalUrl,
		public readonly string $title,
		public readonly string $content,
		public readonly string $summary = '',
		public readonly array $authors = array(),
		public readonly ?\DateTimeImmutable $publishedAt = null,
		public readonly ?\DateTimeImmutable $updatedAt = null,
		public readonly array $categories = array(),
		public readonly array $tags = array(),
		public readonly array $media = array(),
		public readonly ?string $canonicalUrl = null,
		public readonly ?string $language = null,
		public readonly array $extensions = array(),
	) {}

	/** @return array<string,mixed> */
	public function jsonSerialize(): array {
		return array(
			'guid'               => $this->guid,
			'original_url'       => $this->originalUrl,
			'title'              => $this->title,
			'content'            => $this->content,
			'summary'            => $this->summary,
			'author'             => $this->authors[0] ?? '',
			'authors'            => $this->authors,
			'published_at'       => $this->publishedAt?->format( DATE_ATOM ),
			'updated_at'         => $this->updatedAt?->format( DATE_ATOM ),
			'categories'         => $this->categories,
			'tags'               => $this->tags,
			'media'              => $this->media,
			'featured_image_url' => $this->media[0]['url'] ?? '',
			'canonical_url'      => $this->canonicalUrl,
			'language'           => $this->language,
			'extensions'         => $this->extensions,
		);
	}

	public function fingerprint(): string {
		return hash( 'sha256', strtolower( trim( wp_strip_all_tags( $this->title ) ) ) . "\n" . trim( wp_strip_all_tags( $this->content ) ) );
	}
}
