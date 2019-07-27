The Arsse is a news aggregator server which implements multiple synchronization protocols. Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients. Supported protocols are:

- [NextCloud News](https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md)
- [Tiny Tiny RSS](https://git.tt-rss.org/git/tt-rss/wiki/ApiReference)
- [Fever](https://web.archive.org/web/20161217042229/https://feedafever.com/api)

At present the software should be considered in an "alpha" state: many features one would expect from other similar software have yet to be implemented. Areas of future work include:

- Providing more sync protocols (Google Reader, others)
- Better packaging and configuration samples
