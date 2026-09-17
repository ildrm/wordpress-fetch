# Scheduling and WP-CLI

WP-Cron checks due sources every five minutes, but it is traffic-driven and not an exact scheduler. `manual` sources are excluded from automatic scheduling.

For production, run this from system cron at the desired interval:

```cron
*/5 * * * * cd /path/to/wordpress && wp feed-syndicator queue run --limit=25 --quiet
```

Commands:

```bash
wp feed-syndicator source list
wp feed-syndicator source fetch 12
wp feed-syndicator source test 12
wp feed-syndicator queue run --limit=25
wp feed-syndicator queue failed
wp feed-syndicator queue retry 300
wp feed-syndicator health
wp feed-syndicator stats
```

Temporary network and 5xx failures retry with bounded exponential backoff and jitter. HTTP 429 honors usable `Retry-After`. Configuration errors and permanent 401/403/404/410 failures stop retrying.

