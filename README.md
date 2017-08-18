The Advanced RSS Environment
===============================

The Arsse is a news aggregator server which implements [version 1.2](https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md) of [NextCloud News](https://github.com/nextcloud/news)'s client-server synchronization protocol. Unlike most other aggregator servers, the Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

At present the software should be considered in an "alpha" state: though its core subsystems are covered by unit tests and should be free of major bugs, not everything has been rigorously tested. Additionally, though the NextCloud News protocol is fully supported, many features one would expect from other similar software have yet to be implemented. Areas of future work include:

- Support for more database engines (PostgreSQL, MySQL, MariaDB)
- Providing more sync protocols (Tiny Tiny RSS, Fever, others)
- Tools for managing users (manual insertion into the database is currently required)
- Better packaging and configuration samples

Requirements
------------

Arsse has the following requirements:

- A Web server
- PHP 7.0.7 or newer with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php), [json](http://php.net/manual/en/book.json.php), and [hash](http://php.net/manual/en/book.hash.php)
    - [dom](http://php.net/manual/en/book.dom.php), [simplexml](http://php.net/manual/en/book.simplexml.php), and [iconv](http://php.net/manual/en/book.iconv.php) (for picoFeed)
    - [sqlite3](http://php.net/manual/en/book.sqlite3.php)
- The ability to run daemon processes on the server

Installation
------------

TODO: Work out how the system should be installed

If installing from the Git repository rather than a download package, you will need [Composer](https://getcomposer.org/) to fetch required PHP libraries. Once Composer is installed, dependencies may be downloaded with the following command:

``` sh
php composer.phar install -o --no-dev
```

License
-------

Arsse is made available under the permissive MIT license.  See the LICENSE file included with the distribution or source code for exact legal text. Dependencies included in the distribution may be governed by other licenses.

Running tests
-------------

To run the test suite, you must have [Composer](https://getcomposer.org/) installed as well as the command-line PHP interpreter (this is normally required to use Composer). Port 8000 must also be available for use by the built-in PHP Web server.

``` sh
# first install dependencies
php composer.phar install
# run the tests
./tests/test
```

The example uses Unix syntax, but the test suite also runs in Windows. By default all tests are run; you can pass the same arguments to the test runner [as you would to PHPUnit](https://phpunit.de/manual/current/en/textui.html#textui.clioptions):

``` sh
./tests/test --testsuite "Configuration"
```
