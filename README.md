# The Advanced RSS Environment

The Arsse is a news aggregator server which implements multiple synchronization protocols, including [version 1.2][NCNv1] of [NextCloud News][NCN]' protocol and the [Tiny Tiny RSS][TTRSS] protocol (details below). Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

At present the software should be considered in an "alpha" state: though its core subsystems are covered by unit tests and should be free of major bugs, not everything has been rigorously tested. Additionally, many features one would expect from other similar software have yet to be implemented. Areas of future work include:

- Support for more database engines (PostgreSQL, MySQL, MariaDB)
- Providing more sync protocols (Google Reader, Fever, others)
- Complete tools for managing users
- Better packaging and configuration samples

## Requirements

The Arsse has the following requirements:

- A Linux server utilizing systemd and Nginx (tested on Ubuntu 16.04)
- PHP 7.0.7 or later with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php), [json](http://php.net/manual/en/book.json.php), [hash](http://php.net/manual/en/book.hash.php), and [pcre](http://php.net/manual/en/book.pcre.php)
    - [dom](http://php.net/manual/en/book.dom.php), [simplexml](http://php.net/manual/en/book.simplexml.php), and [iconv](http://php.net/manual/en/book.iconv.php) (for picoFeed)
    - [sqlite3](http://php.net/manual/en/book.sqlite3.php) or [pdo_sqlite](http://ca1.php.net/manual/en/ref.pdo-sqlite.php)
- Privileges to create and run daemon processes on the server

## Installation

At present, installation of The Arsse is rather manual. We hope to improve this in the future, but for now the steps below should help get you started. The instructions and configuration samples assume you will be using Ubuntu 16.04 (or equivalent Debian) and Nginx; we hope to expand official support for different configurations in the future as well.

### Initial setup

1. Extract the tar archive to `/usr/share`
2. If desired, create `/usr/share/arsse/config.php` using  `config.defaults.php` as a guide. The file you create only needs to contain non-default settings. The `userPreAuth` setting may be of particular interest
3. Copy `/usr/share/arsse/dist/arsse.service` to `/lib/systemd/system`
4. In a terminal, execute the following to start the feed fetching service:

``` sh
sudo systemctl enable arsse
sudo systemctl start arsse
```

### Configuring the Web server and PHP 

Sample configuration parameters for Nginx can be found in `arsse/dist/nginx.conf` and `arsse/dist/nginx-fcgi.conf`; the samples assume [a server group](http://nginx.org/en/docs/http/ngx_http_upstream_module.html#upstream) has already been defined for PHP. How to configure an Nginx service to use PHP and install the required PHP extensions is beyond the scope of this document, however.

### Adding users

The Arsse currently includes a `user add <username> [<password>]` console command to add users to the database; other user management tasks require manual database edits. Alternatively, if the Web server is configured to handle authentication, you may set the configuration option `userPreAuth` to `true` and The Arsse will defer to the server and automatically add any missing users as it encounters them.

## Installation from source

If installing from the Git repository rather than a download package, you will need to follow extra steps before the instructions in the section above.

First, you must install [Composer] to fetch required PHP libraries. Once Composer is installed, dependencies may be downloaded with the following command:

``` sh
php composer.phar install -o --no-dev --no-scripts
```

Second, you may wish to create an example configuration file using the following command:

``` sh
php ./arsse.php conf save-defaults "./config.defaults.php"
```

## License

The Arsse is made available under the permissive MIT license.  See the `LICENSE` and `AUTHORS` files included with the distribution or source code for exact legal text and copyright holders. Dependencies included in the distribution may be governed by other licenses.

## Contributing

Please refer to `CONTRIBUTING.md` for guidelines on contributing code to The Arsse.

## Protocol compatibility notes

###  General

#### Type casting

The Arsse does not guarantee it will handle type casting of input in the same way as reference implementations for its supported protocols. As a general rule, clients should endeavour to send only correct input.

The Arsse does, however, guarantee _output_ to be of the same type. If it is not, this is [a bug][newIssue] and should be reported.

#### Content sanitization

The Arsse makes use of the [picoFeed] newsfeed parsing library to sanitize article content. The exact sanitization parameters may differ from those of reference implementations for protocols The Arsse supports.

### NextCloud News v1.2

As a general rule, The Arsse should yield the same output as the reference implementation for all valid inputs (otherwise you've found [a bug][newIssue]), but there are exception, either because the NextCloud News (hereafter "NCN") [protocol description][NCNv1] is at times ambiguous or incomplete, or because implementation details necessitate it differ; this section along with the General section above detail these differences.

#### Missing features

- The Arsse does not implement [Cross-Origin Resource Sharing][CORS]

#### Differences

- Article GUID hashes are not hashes like in NCN; they are integers rendered as strings
- Article fingerprints are a combination of hashes rather than a single hash
- When marking articles as starred the feed ID is ignored, as they are not needed to establish uniqueness
- The feed updater ignores the `userId` parameter: feeds in The Arsse are deduplicated, and have no owner
- The `/feeds/all` route lists only feeds which should be checked for updates, and it also returns all `userId` attributes as empty strings: feeds in The Arsse are deduplicated, and have no owner
- The updater console commands mentioned in the protocol specification are not implemented, as The Arsse does not implement the required NextCloud subsystems
- The `lastLoginTimestamp` attribute of the user metadata is always the current time: The Arsse's implementation of the protocol is fully stateless

#### Ambiguities

- NCN does not specify an output character encoding, but the reference server uses UTF-8; The Arsse also uses UTF-8
- NCN specifies that GET parameters are treated "the same" as request body parameters; it does not specify what to do in cases where they conflict. The Arsse chooses to give GET parameters precedence
- NCN does not define validity of folder and names other than to say that the empty string is invalid. The Arsse further considers any string composed only of whitesapce to be invalid
- NCN does not specify a return code for bulk-marking operations without a `newestItemId` provided; The Arsse returns `422`
- NCN does not specify what should be done when creating a feed in a folder which does not exist; the Arsse adds the feed to the root folder
- NCN does not specify what should be done when moving a feed to a folder which does not exist; The Arsse return `422`
- NCN does not specify what should be done when renaming a feed to an invalid title, nor what constitutes an invalid title; The Arsse uses the same rules as it does for folders, and returns `422` in cases of rejection

### Tiny Tiny RSS

As a general rule, The Arsse should yield the same output as the reference implementation for all valid inputs (otherwise you've found [a bug][newIssue]), but there are exception, either because the Tiny Tiny RSS (hereafter "TTRSS") [protocol description][TTRSS] is incomplete, erroneous, or out of date, or because TTRSS itself is buggy, or because implementation details necessitate The Arsse differ; this section along with the General section above detail these differences.

#### Extended functionality

The Arsse supports both [the set of extensions][ext-feedreader] to the TTRSS protocol defined by [FeedReader], as well as [the `getCompactHeadlines` operation][ext-newsplus] defined by [News+].

We are not aware of any other extensions to the TTRSS protocol. If you know of any more, please [let us know][newIssue].

#### Missing features

- The `getPref` operation is not implemented; it returns `UNKNOWN_METHOD`
- The `shareToPublished` operation is not implemented; it returns `UNKNOWN_METHOD`
- Setting an article's "published" flag with the `updateArticle` operation is not implemented and will gracefully fail
- The `search` parameter of the `getHeadlines` operation is not implemented; the operation will proceed as if no search string were specified
- The `sanitize`, `force_update`, and `has_sandbox` parameters of the `getHeadlines` operation are ignored
- String `feed_id` values for the `getCompactHeadlines` operation are not supported and will yield an `INCORRECT_USAGE` error
- Articles are limited to a single attachment rather than multiple attachments

#### Differences

- Input that cannot be parsed as JSON normally returns a `NOT_LOGGED_IN` error; The Arsse returns a non-standard `MALFORMED_INPUT` error instead
- Feed, category, and label names are normally unrestricted; The Arsse rejects empty strings, as well as strings composed solely of whitespace
- Discovering multiple feeds during `subscribeToFeed` processing normally produces an error; The Arsse instead chooses the first feed it finds
- Providing the `setArticleLabel` operation with an invalid label normally silently fails; The Arsse returns an `INVALID_USAGE` error instead
- Article hashes are normally SHA1; The Arsse uses SHA256 hashes
- Article attachments normally have unique IDs; The Arsse always gives attachments an ID of `"0"`
- The default sort order of the `getHeadlines` operation normally uses custom sorting for "special" feeds; The Arsse's default sort order is equivalent to `feed_dates` for all feeds
- The `getCounters` operation normally omits members with zero unread; The Arsse includes everything to appease some clients

#### Errors and ambiguities

- TTRSS accepts base64-encoded passwords, though this is undocumented; The Arsse accepts base64-encoded passwords as well
- TTRSS sometimes returns an incorrect count from the `setArticleLabel` operation; The Arsse returns a correct count in all cases
- TTRSS sometimes returns out-of-date cached information; The Arsse does not use caches as TTRSS does, so information is always current
- TTRSS returns results for _feed_ ID `-3` when providing the `getHeadlines` operation with _category_ ID `-3`; The Arsse retuns the correct results
- The protocol doucmentation advises not to use `limit` or `skip` together with `unread_only` for the `getFeeds` operation as it produces unpredictable results; The Arsse produces predictable results
- The protocol documentation on values for the `view_mode` parameter of the `getHeadlines` operation is out of date; The Arsse matches the actual implementation and supports the undocumented `published` and `has_note` values
- The protocol documentation makes mention of a `search_mode` parameter for the `getHeadlines` operation, but this seems to be ignored; The Arsse does not implement it
- The protocol documentation makes mention of an `output_mode` parameter for the `getCounters` operation, but this seems to be ignored; The Arsse does not implement it
- The documentation for the `getCompactHeadlines` operation states the default value for `limit` is 20, but the reference implementation defaults to unlimited; The Arsse also defaults to unlimited
- It is assumed TTRSS exposes undocumented behaviour; unless otherwise noted The Arsse only implements documented behaviour


[newIssue]: https://code.mensbeam.com/MensBeam/arsse/issues/new
[Composer]: https://getcomposer.org/
[picoFeed]: https://github.com/miniflux/picoFeed/
[NCN]: https://github.com/nextcloud/news
[NCNv1]: https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md
[CORS]: https://fetch.spec.whatwg.org/#http-cors-protocol
[TTRSS]: https://git.tt-rss.org/git/tt-rss/wiki/ApiReference
[FeedReader]: https://jangernert.github.io/FeedReader/
[News+]: https://github.com/noinnion/newsplus/
[ext-feedreader]: https://github.com/jangernert/FeedReader/tree/master/data/tt-rss-feedreader-plugin
[ext-newsplus]: https://github.com/hrk/tt-rss-newsplus-plugin