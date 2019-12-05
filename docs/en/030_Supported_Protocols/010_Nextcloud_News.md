[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.1.0</dd>
    <dt>Base URL</dt>
        <dd>/</dd>
    <dt>API endpoint</dt>
        <dd>/index.php/apps/news/api/v1-2/</dd>
    <dt>Specifications</dt>
        <dd><a href="https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md">Version 1.2</a></dd>
</dl>

The Nextcloud News protocol was the first supported by The Arsse, and has been supported in full since version 0.3.0.

It allows organizing newsfeeds into single-level folders, and supports a wide range of operations on newsfeeds, folders, and articles.

# Differences

- Article GUID hashes are not hashes like in NCN; they are integers rendered as strings
- Article fingerprints are a combination of hashes rather than a single hash
- When marking articles as starred the feed ID is ignored, as they are not needed to establish uniqueness
- The feed updater ignores the `userId` parameter: feeds in The Arsse are deduplicated, and have no owner
- The `/feeds/all` route lists only feeds which should be checked for updates, and it also returns all `userId` attributes as empty strings: feeds in The Arsse are deduplicated, and have no owner
- The API's "updater" routes do not require administrator priviledges as The Arsse has no concept of user classes
- The "updater" console commands mentioned in the protocol specification are not implemented, as The Arsse does not implement the required Nextcloud subsystems
- The `lastLoginTimestamp` attribute of the user metadata is always the current time: The Arsse's implementation of the protocol is fully stateless
- Syntactically invalid JSON input will yield a `400 Bad Request` response instead of falling back to GET parameters
- Folder names consisting only of whitespace are rejected along with the empty string
- Feed titles consisting only of whitespace or the empty string are rejected with a `422 Unprocessable Entity` reponse instead of being accepted
- Bulk-marking operations without a `newestItemId` argument result in a `422 Unprocessable Entity` reponse instead of silently failing
- Creating a feed in a folder which does not exist places the feed in the root folder rather than suppressing the feed
- Moving a feed to a folder which does not exist results in a `422 Unprocessable Entity` reponse rather than suppressing the feed

# Interaction with nested folders

Tiny Tiny RSS is unique in allowing newsfeeds to be grouped into folders nested to arbitrary depth. When newsfeeds are placed into nested folders, they simply appear in the top-level folder when accessed via the Nextcloud News protocol.
