[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.2.0</dd>
    <dt>Base URL</dt>
        <dd>/tt-rss/</dd>
    <dt>API endpoint</dt>
        <dd>/tt-rss/api</dd>
    <dt>Specifications</dt>
        <dd><a href="https://tt-rss.org/wiki/ApiReference">Main</a>, <a href="https://tt-rss.org/wiki/SearchSyntax">search syntax</a>, <a href="https://github.com/jangernert/FeedReader/blob/master/data/tt-rss-feedreader-plugin/README.md">FeedReader extensions</a>, <a href="https://github.com/hrk/tt-rss-newsplus-plugin/blob/master/README.md">News+ extension</a></dd>
</dl>

The Arsse supports not only the Tiny Tiny RSS protocol, but also extensions required by the FeedReader client and the more commonly supported `getCompactHeadlines` extension.

It allows organizing newsfeeds into nested folders, and supports an odd patchwork subset of Tiny Tiny RSS' full capabilities. The FeedReader extensions round out the protocol with significantly more features. Unlike with TT-RSS itself, API access is always enabled with The Arsse.

# Missing features

The Arsse does not currently support the entire protocol. Notably missing features include manipulation of the special "Published" newsfeed, as well as searching. The full list of missing features is as follows:

- The `shareToPublished` operation is not implemented; it returns `UNKNOWN_METHOD`
- Setting an article's "published" flag with the `updateArticle` operation is not implemented and will gracefully fail
- The `sanitize`, `force_update`, and `has_sandbox` parameters of the `getHeadlines` operation are ignored
- String `feed_id` values for the `getCompactHeadlines` operation are not supported and will yield an `INCORRECT_USAGE` error
- Articles are limited to a single attachment rather than multiple attachments
- The `getPref` operation is not implemented; it returns `UNKNOWN_METHOD`

# Differences

- Input that cannot be parsed as JSON normally returns a `NOT_LOGGED_IN` error; The Arsse returns a non-standard `MALFORMED_INPUT` error instead
- Feed, category, and label names are normally unrestricted; The Arsse rejects empty strings, as well as strings composed solely of whitespace
- Discovering multiple feeds during `subscribeToFeed` processing normally produces an error; The Arsse instead chooses the first feed it finds
- Providing the `setArticleLabel` operation with an invalid label normally silently fails; The Arsse returns an `INVALID_USAGE` error instead
- Processing of the `search` parameter of the `getHeadlines` operation differs in the following ways:
    - Values other than `"true"` or `"false"` for the `unread`, `star`, and `pub` special keywords treat the entire token as a search term rather than as `"false"`
    - Invalid dates are ignored rather than assumed to be `"1970-01-01"`
    - Specifying multiple non-negative dates usually returns no results as articles must match all specified dates simultaneously; The Arsse instead returns articles matching any of the specified dates
    - Dates are always relative to UTC
    - Full-text search is not yet employed with any database, including PostgreSQL
- Article hashes are normally SHA1; The Arsse uses SHA256 hashes
- Article attachments normally have unique IDs; The Arsse always gives attachments an ID of `"0"`
- The `getCounters` operation normally omits members with zero unread; The Arsse includes everything to appease some clients

# Other notes

- TT-RSS accepts base64-encoded passwords, though this is undocumented; The Arsse accepts base64-encoded passwords as well
- TT-RSS sometimes returns an incorrect count from the `setArticleLabel` operation; The Arsse returns a correct count in all cases
- TT-RSS sometimes returns out-of-date cached information; The Arsse does not use caches as TT-RSS does, so information is always current
- TT-RSS returns results for _feed_ ID `-3` when providing the `getHeadlines` operation with _category_ ID `-3`; The Arsse retuns the correct results
- The protocol doucmentation advises not to use `limit` or `skip` together with `unread_only` for the `getFeeds` operation as it produces unpredictable results; The Arsse produces predictable results by first retrieving all unread feeds and then applying `skip` and `limit`
- The protocol documentation on values for the `view_mode` parameter of the `getHeadlines` operation is out of date; The Arsse matches the actual implementation and supports the undocumented `published` and `has_note` values exposed by the Web user interface
- The protocol documentation makes mention of a `search_mode` parameter for the `getHeadlines` operation, but this seems to be ignored; The Arsse does not implement it
- The protocol documentation makes mention of an `output_mode` parameter for the `getCounters` operation, but this seems to be ignored; The Arsse does not implement it
- The documentation for the `getCompactHeadlines` operation states the default value for `limit` is 20, but the reference implementation defaults to unlimited; The Arsse also defaults to unlimited
- It is assumed TT-RSS exposes other undocumented behaviour; unless otherwise noted The Arsse only implements documented behaviour

# Interaction with HTTP authentication

Tiny Tiny RSS itself is unaware of HTTP authentication: if HTTP authentication is used in the server configuration, it has no effect on authentication in the API. The Arsse, however, makes use of HTTP authentication for Nextcloud News, and can do so for TT-RSS as well. In a default configuration The Arsse functions in the same way as TT-RSS: HTTP authentication and API authentication are completely separate and independent. Alternative behaviour is summarized below:

- With default settings:
    - Clients may optionally provide HTTP credentials
    - API authentication proceeds as normal
    - All feed icons are visible to unauthenticated clients
    - Analogous to multi-user mode
- If the `userHTTPAuthRequired` setting is `true`:
    - Clients must pass HTTP authentication
    - API authentication proceeds as normal
    - Feed icons are visible only to their owners
    - Analoguous to multi-user mode with additional HTTP authentication
- If the `userSessionEnforced` setting is `false`:
    - Clients may optionally provide HTTP credentials
    - If HTTP authentication succeeded API authentication is skipped: tokens are issued upon login, but ignored for HTTP-authenticated requests
    - All feed icons are visible to unauthenticated clients
    - Analogous to single-user mode if using HTTP authentication, and to multi-user mode otherwise
- If the `userHTTPAuthRequired` setting is `true` and the `userSessionEnforced` setting is `false`:
    - Clients must pass HTTP authentication
    - API authentication is skipped: tokens are issued upon login, but thereafter ignored
    - Feed icons are visible only to their owners
    - Analogous to single-user mode
- If the `userPreAuth` setting is `true`:
    - The Web server asserts HTTP authentication was successful
    - API authentication only checks that HTTP and API user names match
    - Feed icons are visible only to their owners
    - Analoguous to multi-user mode with additional HTTP authentication
- If the `userPreAuth` setting is `true` and the `userSessionEnforced` setting is `false`:
    - The Web server asserts HTTP authentication was successful
    - API authentication is skipped: tokens are issued upon login, but thereafter ignored
    - Feed icons are visible only to their owners
    - Analogous to single-user mode

In all cases, supplying invalid HTTP credentials will result in a 401 response.
