[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.8.0</dd>
    <dt>Base URL</dt>
        <dd>/fever/</dd>
    <dt>API endpoint</dt>
        <dd>/fever/?api</dd>
    <dt>Specifications</dt>
        <dd><a href="https://web.archive.org/web/20161217042229/https://feedafever.com/api">"Public beta"</a> (at the Internet Archive)</dd>
</dl>

The Fever protocol is a basic protocol which has historically been popular with iOS and macOS clients.

It allows marking articles as read or starred, but does not allow adding or modifying newsfeeds. Moreover, instead of being classified into folders, newfeeds may belong to multiple groups, which do not nest.

# Missing features

The Fever protocol is incomplete, unusual, _and_ a product of proprietary software which is no longer available. Conssequently some features have been omitted either out of necessity or because implementation details made the effort required too great.

- All feeds are considered "Kindling"
- The "Hot Links" feature is not implemented; when requested, an empty array will be returned. As there is no way to classify a feed as a "Spark" in the protocol itself and no documentation exists on how link temperature was calculated, an implementation is unlikely to appear in the future

# Special considerations

- Because of Fever's unusual and insecure authentication scheme, a Fever-specific password [must be created](/en/Using_The_Arsse/Managing_Users) before a user can communicate via the Fever protocol
- The Fever protocol does not allow for adding or modifying feeds. Another protocol or OPML importing must be used to manage feeds
- Unlike other protocols supported by The Arsse, Fever uses "groups" (more commonly known as tags or labels) instead of folders to organize feeds. Currently [OPML importing](/en/Using_The_Arsse/Importing_and_Exporting) is the only means of managing groups

# Other notes

- The undocumented `group_ids`, `feed_ids`, and `as=unread` parameters are all supported
- XML output is supported, but may not behave as Fever did. Its use by clients is discouraged

# Interaction with HTTP Authentication

Fever was not designed with HTTP authentication in mind, and few clients respond to challenges. If the Web server or The Arsse is configured to require successful HTTP authentication, most Fever clients are not likely to be able to connect properly.

# Interaction with Folders

Unlike other protocols supported by The Arsse, Fever uses "groups" (more commonly known as tags or labels) to organize newsfeeds. These are fully supported and are exposed as categories in OPML import and export. They are treated separately from folders.
