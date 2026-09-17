=== WordPress Fetch ===
Contributors: ildrm
Tags: rss, atom, feed, syndication, aggregator
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely import RSS 2.0, RSS/RDF, Atom, and OPML sources into dynamic WordPress post types.

== Description ==

WordPress Fetch is a feed-ingestion platform with normalized parsing, rules, templates, duplicate prevention, editorial locks, media handling, background jobs, explicit SEO policies, monitoring, REST endpoints, and WP-CLI commands.

Remote data is treated as untrusted. Private-network destinations, dangerous XML declarations, hostile HTML, and unsafe media are rejected or sanitized. The default publication status is draft and the default syndicated-content policy uses an original-source canonical with noindex/follow.

== Installation ==

1. Upload and activate the plugin.
2. Open WP Fetch > Add source.
3. Test the connection and preview real items.
4. Choose destination, publication, media, SEO, and schedule policies.
5. Save the source. Large work runs through the internal queue.

For reliable production schedules, call `wp feed-syndicator queue run --limit=25` from system cron.

== Frequently Asked Questions ==

= Does RSS availability grant republication rights? =

No. Confirm the publisher's license or permission before importing full content.

= Why did a schedule run late? =

WP-Cron is traffic-driven. Use a real cron job and WP-CLI when exact timing matters.

= Are credentials exported? =

No. Credentials are encrypted at rest when Libsodium is available, masked in the UI, redacted from logs, and omitted from exports.

== Changelog ==

= 1.0.0 =
* Initial production release.

