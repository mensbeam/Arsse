# The Advanced RSS Environment

The Arsse is a news aggregator server which implements [version 1.2](https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md) of [NextCloud News](https://github.com/nextcloud/news)' client-server synchronization protocol. Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

At present the software should be considered in an "alpha" state: though its core subsystems are covered by unit tests and should be free of major bugs, not everything has been rigorously tested. Additionally, though the NextCloud News protocol is fully supported, many features one would expect from other similar software have yet to be implemented. Areas of future work include:

- Support for more database engines (PostgreSQL, MySQL, MariaDB)
- Providing more sync protocols (Tiny Tiny RSS, Fever, others)
- Complete tools for managing users
- Better packaging and configuration samples

## Requirements

The Arsse has the following requirements:

- A Web server
- PHP 7.0.7 or newer with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php), [json](http://php.net/manual/en/book.json.php), [hash](http://php.net/manual/en/book.hash.php), and [pcre](http://php.net/manual/en/book.pcre.php)
    - [dom](http://php.net/manual/en/book.dom.php), [simplexml](http://php.net/manual/en/book.simplexml.php), and [iconv](http://php.net/manual/en/book.iconv.php) (for picoFeed)
    - [sqlite3](http://php.net/manual/en/book.sqlite3.php)
- The ability to run daemon processes on the server

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

First, you must install [Composer](https://getcomposer.org/) to fetch required PHP libraries. Once Composer is installed, dependencies may be downloaded with the following command:

``` sh
php composer.phar install -o --no-dev
```

Second, you may wish to create an example configuration file using the following command:

``` sh
php ./arsse.php conf save-defaults "./config.defaults.php"
```

## License

The Arsse is made available under the permissive MIT license.  See the `LICENSE` file included with the distribution or source code for exact legal text. Dependencies included in the distribution may be governed by other licenses.

## Contributing

Please refer to `CONTRIBUTING.md` for guidelines on contributing code to The Arsse.

### Running tests

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
