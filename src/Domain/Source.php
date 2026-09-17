<?php
declare(strict_types=1);

namespace WordPressFetch\Domain;

final class Source {
	/**
	 * @param array<string,mixed> $config Source configuration.
	 * @param array<string,mixed> $credentials Decrypted server-side credentials.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $feedUrl,
		public readonly string $postType = 'post',
		public readonly string $postStatus = 'draft',
		public readonly int $authorId = 1,
		public readonly array $config = array(),
		public readonly ?string $etag = null,
		public readonly ?string $lastModified = null,
		public readonly array $credentials = array(),
	) {}
}
