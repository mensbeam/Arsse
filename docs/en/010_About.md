The Arsse is a news aggregator server which implements multiple synchronization protocols, including [version 1.2](https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md) of [NextCloud News](https://github.com/nextcloud/news)' protocol and the [Tiny Tiny RSS](https://git.tt-rss.org/git/tt-rss/wiki/ApiReference) protocol (details below). Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

At present the software should be considered in an "alpha" state: though its core subsystems are covered by unit tests and should be free of major bugs, not everything has been rigorously tested. Additionally, many features one would expect from other similar software have yet to be implemented. Areas of future work include:

- Providing more sync protocols (Google Reader, Fever, others)
- Better packaging and configuration samples
