# Troubleshooting

- **Timeout:** verify publisher availability, DNS, and outbound firewall rules. Increase timeout only after ruling out a dead source.
- **Unsafe URL:** the destination or one of its DNS addresses is local, private, reserved, link-local, or malformed. WordPress Fetch will not bypass this protection.
- **401/403:** update the source authentication profile. Existing imported posts remain untouched.
- **429:** leave the source enabled; the queue backs off according to `Retry-After` where possible.
- **Invalid XML:** inspect the publisher response. HTML error pages, DOCTYPE/entity declarations, and malformed XML are rejected.
- **Late schedule:** use system cron with WP-CLI; WP-Cron depends on traffic.
- **Partial import:** the post succeeded but a later stage such as media failed. Retrying resumes against the existing import identity.
- **Repeated duplicate:** inspect GUID and URL stability. Identity uses source/GUID, normalized URL, and content hash rather than title.

