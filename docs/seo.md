# SEO behavior

Each imported post can hold a canonical policy and robots directives. The default syndicated-copy preset stores the original URL as canonical and uses `noindex,follow`. Noindex imported posts are excluded from the native WordPress sitemap query.

Provider detection supports Yoast SEO, Rank Math, All in One SEO, and SEOPress. When one is active, WordPress Fetch does not print its own canonical, Open Graph, or Article JSON-LD. It supplies the imported canonical through that provider's frontend filter. With no provider, the native implementation prints one canonical, basic Open Graph fields, and an Article object containing only available fields.

Policies:

- **Original source:** imported copy points to the publisher.
- **Feed provided:** uses a supported feed canonical when present, otherwise the original article.
- **Local:** leaves the imported canonical meta blank so WordPress or the active provider owns the local URL.

Canonical directives do not guarantee indexing behavior. Review full-content + local-canonical + index configurations carefully.

