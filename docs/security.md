# Security model

All remote URLs and bytes are untrusted.

- Only HTTP/HTTPS URLs accepted by `wp_http_validate_url()` are allowed.
- DNS A/AAAA results are rejected if loopback, private, reserved, or link-local; numeric-encoded hosts are rejected.
- `wp_safe_remote_request()` revalidates redirects and limits response bytes, timeout, and redirect count.
- XML is fetched as bytes, DOCTYPE and ENTITY declarations are rejected, and DOM parses locally with `LIBXML_NONET`, without entity substitution. XML size and nesting are bounded.
- Remote HTML is stripped of script/style/object/embed/SVG/MathML/iframe content and passed through `wp_kses_post()`.
- Media URLs receive the same network checks. Downloads are size-bounded, extension/MIME checked, allowlisted, and stored through WordPress media APIs.
- Source secrets use Libsodium secretbox with a key derived from server-side WordPress salts or `WPFETCH_SECRET_KEY`. Base64 is transport encoding, not the protection mechanism.
- Secrets are never returned by REST, copied during cloning, exported, or stored in normal log context. Logger keys and bearer/basic values are redacted.
- Every private REST route has a capability permission callback. Non-REST editor writes require both nonce and post capability.
- SQL values are prepared; dynamic table names come only from internal allowlists.

For best isolation, define a strong `WPFETCH_SECRET_KEY` outside version control, keep WordPress salts stable, run HTTPS, and give operational capabilities only to trusted roles.

