# WordPress Fetch

WordPress Fetch is a modular WordPress feed-syndication plugin for RSS 2.0, RSS/RDF, Atom, and OPML. It normalizes remote entries before applying filters, mappings, deduplication, editorial policy, media handling, WordPress persistence, and SEO policy.

## Requirements

- WordPress 6.6 or newer
- PHP 8.1 or newer with DOM and Libsodium
- HTTPS and a real cron runner are strongly recommended for production

## Quick start

Install `build/wordpress-fetch-1.0.0.zip`, activate it, and open **WP Fetch → Add source**. The 11-step wizard can discover declared feeds, test a source before saving, dynamically select writable post types, preview entries, and explain publication and SEO consequences.

For deterministic scheduling:

```bash
wp feed-syndicator queue run --limit=25
```

## Development

```bash
composer install
composer lint
composer test
composer analyse
bash scripts/package.sh
```

The plugin includes a PSR-4 fallback loader, so Composer is not needed at runtime. See [architecture](docs/architecture.md), [security](docs/security.md), [SEO](docs/seo.md), [REST and hooks](docs/developer.md), and the [test report](docs/test-report.md).

## Data ownership

Disabling or deleting a source preserves imported posts. Uninstall preserves all plugin data by default. A site owner must deliberately change `uninstall_policy` to `config_only` or `full_cleanup` before uninstalling.
