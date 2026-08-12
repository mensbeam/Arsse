[TOC]

# About

<dl>
    <dt>Supported since</dt>
        <dd>0.13.0</dd>
    <dt>Base URL</dt>
        <dd>/ (FeedHQ)</dd>
        <dd>/api/greader.php/ (FreshRSS)</dd>
    <dt>API endpoint</dt>
        <dd>/reader/api/0/</dd>
        <dd>/accounts/ClientLogin</dd>
        <dd>/api/greader.php/reader/api/0/</dd>
        <dd>/api/greader.php/accounts/ClientLogin</dd>
    <dt>Specifications</dt>
        <dd>N/A</dd>
</dl>

The "Open Reader" protocol is a poorly-documented though widely used protocol, particularly (though not exclusively) by commercial services since the shuttering of the original Google Reader itself in 2013. Nearly all open-source implementations aim for compatibility with how [FreshRSS](https://freshrss.org) has implemented the protocol, thus The Arsse also primarily replicates FreshRSS' functionality.

However, there also exists [FeedHQ](https://feedhq.readthedocs.io/en/latest/), an independent open-source server implementation. While it has not been maintained since 2018 and few compatible clients are known to exist, The Arsse does make an effort to be compatible with FeedHQ as well.

# Feature notes

- Values other than `o` for the common `r` sorting parameter are ignored
- Splice streams (which appear to be a FeedHQ extension) are supported for all stream-related parameters.
- XML output is supported for the FeedHQ endpoint; Atom output is not
- For compatibility some functionality differs between the FeedHQ and FreshRSS endpoints. Explicitly using the FreshRSS endpoint URL may be required by some FreshRSS clients

# Interaction with folders

Unlike most other protocols supported by The Arsse, Google Reader used "labels" (more commonly known as tags) to organize newsfeeds, which could have multiple labels associated to them. Unlike many other Open Reader implementations which allow only one label per newsfeed, The Arsse supports multiple labels per newsfeed, and they are exposed as categories in OPML import and export. They are treated separately from folders as used by most other protocols supported by The Arsse.
