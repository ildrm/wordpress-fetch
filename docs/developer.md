# Developer API

The REST namespace is `wordpress-fetch/v1`. Resources include sources, source actions, discovery, pre-save testing, metadata, dashboard, jobs, logs, OPML, groups, profiles, settings, and configuration export/validation/import. Browser calls use WordPress cookie authentication and `X-WP-Nonce`; external automation should use Application Passwords.

Important extension points:

- `wpfetch_request_headers( array $headers, string $url, ?Source $source )`
- `wpfetch_mapped_post( array $post, FeedItem $item, Source $source )`
- `wpfetch_post_imported( int $post_id, FeedItem $item, Source $source )`

Normalized parsers return `Domain\FeedItem`; extensions should operate on normalized fields rather than RSS-specific XML paths. Mapping templates do not execute PHP.

Configuration export has schema version `1.0`, excludes credentials, and can be validated before applying. Import requires an explicit `confirm: true`, never imports credentials, and creates sources disabled. OPML export is separate and intentionally contains only source-list information.
