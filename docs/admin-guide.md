# Administrator guide

## Add a source

Open **WP Fetch → Add source**. Enter a feed URL, or enter a website and use discovery. Test the connection before saving; the result shows format, latency, item count, response metadata, and a real preview without persistence.

Choose any writable registered post type. Draft or pending review is recommended until mappings, permissions, and SEO policy are confirmed. Content modes are full feed content, summary, title/link, and a validated template. Unknown template tokens prevent activation.

Media downloading is opt-in. The importer will skip an unsafe asset and retain the article as a partial success. A source that provides only an excerpt is not scraped for the full page.

## SEO and licensing

Original-source canonical plus `noindex,follow` is the conservative syndicated-copy default. Local canonical plus index is suitable only when the site owns or is licensed as the primary copy. Canonicals are hints, not guarantees.

RSS availability is not a republication license. Record permission outside the public content, and use summary/link mode when rights are uncertain.

## Operations

**Fetch now** queues work; it does not hold the browser open. Activity shows redacted, human-readable events. A disabled or deleted source does not remove imported posts. Imported posts expose an editor panel for whole-post and field-level synchronization locks.

OPML is treated only as a source collection. Paste OPML under Tools, preview and select feeds, then import them disabled for individual review.

