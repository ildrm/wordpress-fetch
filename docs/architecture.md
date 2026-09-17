# Architecture

WordPress Fetch uses a layered pipeline:

```text
Safe HTTP → parser adapter → FeedItem normalization → rules → mapping
          → atomic identity reservation → WordPress post/media → SEO metadata
          → import record, health, and structured logs
```

The main bootstrap only defines constants, loads namespaced classes, registers lifecycle hooks, and boots `Core\Plugin`. Domain objects do not know about XML. Feed parsers do not write posts. SQL is contained in schema/repository/queue services. Admin code talks to capability-protected REST controllers.

## Persistence

- `wpfetch_sources`: source configuration, conditional request state, scheduling, and health.
- `wpfetch_groups` / `wpfetch_profiles`: reusable inherited configuration.
- `wpfetch_jobs`: durable queue with lock token, expiry, retries, and terminal states.
- `wpfetch_imports`: durable item identity, WordPress post link, hashes, state, and field protection.
- `wpfetch_logs`: bounded operational events with redacted context.

Per-site `$wpdb->prefix` keeps Multisite ownership explicit. Schema changes are repeat-safe through `dbDelta()` and a stored schema version. Configuration stays non-autoloaded.

## Concurrency and recovery

Workers claim a job in a database transaction using `SELECT … FOR UPDATE`, then attach a unique lock token. Item import inserts a unique `(source_id,guid_hash)` and global `url_hash` reservation before creating a post. A collision becomes a duplicate instead of a second post. If media fails after post creation, the import record becomes `partially_imported`; retry cannot recreate the post.

WordPress post/media operations cannot participate in one database transaction, so import records are the compensating ledger. Whole-post and field locks are checked before synchronization.

## ADRs

1. **Custom tables over CPTs for operations.** Queue and identity lookups need bounded, indexed queries and atomic unique constraints; postmeta is unsuitable at high volume.
2. **Internal queue.** The plugin must not depend on WooCommerce. A small purpose-built queue supplies claims, expiry, backoff, cancellation, and retry.
3. **DOM with `LIBXML_NONET`.** Remote bytes are fetched first through safe HTTP. DOCTYPE/entity declarations are rejected before parsing; network-capable XML parsing is never used.
4. **Native SEO only as fallback.** Recognized SEO plugins own page metadata. WordPress Fetch delegates canonicals through provider filters and suppresses its native head output.
5. **REST-driven admin.** It provides schema-bound authorization and keeps large operations asynchronous while retaining WordPress-native menus and controls.
6. **Runtime fallback autoloader.** Composer describes development dependencies, while the packaged plugin boots without a vendor directory.

