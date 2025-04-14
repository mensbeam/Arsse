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
        <dd><a href="https://miniflux.app/docs/api.html">API Reference</a>, <a href="https://miniflux.app/docs/rules.html#filtering-rules">Filtering Rules</a></dd>
</dl>

The Miniflux protocol is a fairly well-designed protocol supporting a wide variety of operations on newsfeeds, folders (termed "categories"), and articles; it also allows for user administration, and native OPML importing and exporting. Architecturally it is similar to the Nextcloud News protocol, but has more capabilities.

Miniflux version 2.2.7 is emulated, though not all features are implemented

# Missing features

- JSON Feed format is not suported
- Various feed-related features are not supported; attempting to use them has no effect
    - Rewrite rules and scraper rules
    - [Global filtering rules](https://miniflux.app/docs/rules.html#global-filtering-rules) (feed filtering rules are supported)
    - The `disabled`, `hide_globally`, `ignore_http_cache`, and `fetch_via_proxy` flags
    - Manually refreshing feeds
    - Changing the title or content of an entry
- Third-party integrations features are not supported; attempting to use them has no effect
  - Saving entries to third-party services
  - Integrations status (this will always return `false`)
- Titles and types are not available during feed discovery and are filled with generic data
- Reading time is not calculated and will always be zero
- Only the first enclosure of an article is retained
- Comment URLs of articles are not exposed
- The "Flush history" feature does nothing because the API does not seem to expose the history

# Differences

- Various error codes and messages differ due to significant implementation differences
- The "All" category is treated specially (see below for details)
- Feed and category titles consisting only of whitespace are rejected along with the empty string
- Feeds created without a category are placed in the "All" category rather than the most recently modified category
- Feed filtering rules may not function identically (see below for details)
- The `checked_at` field of feeds indicates when the feed was last updated rather than when it was last checked
- Search strings will match partial words
- OPML import either succeeds or fails atomically: if one feed fails, no feeds are imported
- The Arsse does not track sessions for Miniflux, so the `last_login_at` time of users will always be the current time

# Behaviour of feed filtering (block and keep) rules

Miniflux accepts [Google's RE2 regular expression syntax](https://github.com/google/re2/wiki/Syntax) for feed filter rules. Being a PHP application, The Arsse instead accepts [PCRE2 syntax](https://www.pcre.org/current/doc/html/pcre2syntax.html)), specifically in UTF-8 mode. Delimiters should not be included, and slashes should not be escaped; anchors may be used if desired. For example `^(?i)RE/MAX$` is a valid pattern.

For convenience the patterns are tested after collapsing whitespace. Unlike Miniflux, when filter rules are modified they are re-evaluated against all applicable articles immediately.

# Special handling of the "All" category

Nextcloud News' root folder and Tiny Tiny RSS' "Uncategorized" catgory are mapped to Miniflux's initial "All" category. This Miniflux category can be renamed, but it cannot be deleted. Attempting to do so will delete the child feeds it contains, but not the category itself.

Because the root folder does not existing in the database as a separate entity, it will always sort first when ordering by `category_id` or `category_title`.

# Interaction with nested categories

Tiny Tiny RSS is unique in allowing newsfeeds to be grouped into categories nested to arbitrary depth. When newsfeeds are placed into nested categories, they simply appear in the top-level category when accessed via the Miniflux protocol. This does not affect OPML exports, where full nesting is preserved.
