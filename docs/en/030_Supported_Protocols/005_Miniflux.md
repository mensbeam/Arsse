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

The Miniflux protocol is a fairly well-designed protocol supporting a wide variety of operations on newsfeeds, folders (termed "categories"), and articles; it also allows for user administration, and native OPML importing and exporting. Architecturally it is similar to the Nextcloud News protocol, but is generally more efficient and has more capabilities.

Miniflux version 2.0.27 is emulated, though not all features are implemented

# Missing features

- JSON Feed format is not suported
- Various feed-related features are not supported; attempting to use them has no effect
    - Rewrite rules and scraper rules
    - Custom User-Agent strings
    - The `disabled`, `ignore_http_cache`, and `fetch_via_proxy` flags
    - Changing the URL, username, or password of a feed

# Differences

- Various error messages differ due to significant implementation differences
- `PUT` requests which return a body respond with `200 OK` rather than `201 Created`
- Only the URL should be considered reliable in feed discovery results
- The "All" category is treated specially (see below for details)
- Category names consisting only of whitespace are rejected along with the empty string
- Filtering rules may not function identically (see below for details)
- The `checked_at` field of feeds indicates when the feed was last updated rather than when it was last checked

# Behaviour of filtering (block and keep) rules

The Miniflux documentation gives only a brief example of a pattern for its filtering rules; the allowed syntax is described in full [in Google's documentation for RE2](https://github.com/google/re2/wiki/Syntax). Being a PHP application, The Arsse instead accepts [PCRE syntax](http://www.pcre.org/original/doc/html/pcresyntax.html) (or since PHP 7.3 [PCRE2 syntax](https://www.pcre.org/current/doc/html/pcre2syntax.html)), specifically in UTF-8 mode. Delimiters should not be included, and slashes should not be escaped; anchors may be used if desired. For example `^(?i)RE/MAX$` is a valid pattern.

For convenience the patterns are tested after collapsing whitespace. Unlike Miniflux, The Arsse tests the patterns against an article's author-supplied categories if they do not match its title. Also unlike Miniflux, when filter rules are modified they are re-evaluated against all applicable articles immediately.

# Special handling of the "All" category

Nextcloud News' root folder and Tiny Tiny RSS' "Uncategorized" catgory are mapped to Miniflux's initial "All" category. This Miniflux category can be renamed, but it cannot be deleted. Attempting to do so will delete the child feeds it contains, but not the category itself.

# Interaction with nested categories

Tiny Tiny RSS is unique in allowing newsfeeds to be grouped into categories nested to arbitrary depth. When newsfeeds are placed into nested categories, they simply appear in the top-level category when accessed via the Miniflux protocol. This does not affect OPML exports, where full nesting is preserved.
