[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.9.0</dd>
    <dt>Base URL</dt>
        <dd>/</dd>
    <dt>API endpoint</dt>
        <dd>/v1/</dd>
    <dt>Specifications</dt>
        <dd><a href="https://miniflux.app/docs/api.html">API Reference</a></dd>
</dl>

The Miniflux protocol is a well-designed protocol supporting a wide variety of operations on newsfeeds, folders (termed "categories"), and articles; it also allows for user administration, and native OPML importing and exporting. Architecturally it is similar to the Nextcloud News protocol, but is generally more efficient.

Miniflux version 2.0.25 is emulated, though not all features are implemented

# Missing features

- JSON Feed format is not suported
- Various feed-related features are not supported; attempting to use them has no effect
    - Rewrite rules and scraper rules
    - Custom User-Agent strings
    - The `disabled`, `ignore_http_cache`, and `fetch_via_proxy` flags
    - Changing the URL, username, or password of a feed

# Differences

- Only the URL should be considered reliable in feed discovery results

# Interaction with nested folders

Tiny Tiny RSS is unique in allowing newsfeeds to be grouped into folders nested to arbitrary depth. When newsfeeds are placed into nested folders, they simply appear in the top-level folder when accessed via the Miniflux protocol. This does not affect OPML exports, where full nesting is preserved.
