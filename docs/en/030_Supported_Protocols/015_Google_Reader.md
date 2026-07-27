[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.13.0</dd>
    <dt>Base URL</dt>
        <dd>/api/greader.php/</dd>
    <dt>API endpoint</dt>
        <dd>/api/greader.php/reader/api/0/</dd>
        <dd>/api/greader.php/accounts/ClientLogin</dd>
    <dt>Specifications</dt>
        <dd>N/A</dd>
</dl>

The Google Reader protocol is a poorly-documented though widely used protocol, particularly (though not exclusively) by commercial services since the shuttering of Google Reader itself in 2013. Nearly all open-source implementations aim for compatibility with how [FreshRSS](https://freshrss.org) has implemented the protocol, thus The Arsse also primarily replicates FreshRSS' functionality.

There also exists [FeedHQ](https://feedhq.readthedocs.io/en/latest/), an independent open-source server implementation, but it is no longer maintained and no compatible clients are known to exist. Consequently no significant effort has been made to make The Arsse replicate its behaviour, even though some of its features have been implemented.

# Feature notes

- Values other than `o` for the common `r` sorting parameter are ignored
- Splice streams (which appear to be a FeedHQ extension) are supported for all stream-related parameters.
- Multiple features omitted by FreshRSS but supported by FeedHQ have been implemented
- XML output is supported; Atom output is not

# Interaction with Folders

Unlike most other protocols supported by The Arsse, Google Reader used "labels" (more commonly known as tags) to organize newsfeeds, which could have multiple labels associated to them. Unlike many other Google Reader implementations which allow only one label per newsfeed, The Arsse supports multiple labels per newsfeed, and they are exposed as categories in OPML import and export. They are treated separately from folders as used by most other protocols supported by The Arsse.
