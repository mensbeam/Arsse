[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.13.0</dd>
    <dt>Base URL</dt>
        <dd>Generic: /</dd>
        <dd>FreshRSS: /api/greader.php/</dd>
    <dt>API endpoint</dt>
        <dd>Generic: /reader/api/0/</dd>
        <dd>FreshRSS: /api/greader.php/reader/api/0/</dd>
    <dt>Specifications</dt>
        <dd><a href="https://miniflux.app/docs/api.html">API Reference</a>, <a href="https://miniflux.app/docs/rules.html#filtering-rules">Filtering Rules</a></dd>
</dl>

TODO

# Feature notes

- The common `r` sorting parameter supports the values `n` (ascending chronological) and `o` (descending chronological). The `a` and `c` values are ignored
- Splice streams (which appear to be a FeedHQ extension) are supported for the `s` parameter, but not the `xt` or `it` parameters.

# Interaction with Folders

Unlike most other protocols supported by The Arsse, Google Reader used "labels" (more commonly known as tags) to organize newsfeeds, which could have multiple labels associated to them. Unlike many other Google Reader implementations which allow only one label per newsfeed, The Arsse supports multiple labels per newsfeed, and they are exposed as categories in OPML import and export. They are treated separately from folders.
