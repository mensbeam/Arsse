Arsse: Advanced RSS Environment
===============================

TODO: Fill in stuff

Requirements
------------

Arsse has the following requirements:

- A Web server; example configuration currently exists for:
    - nginx
    - Apache 2
- PHP 7.0 or newer with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php)
    - [json](http://php.net/manual/en/book.json.php)
    - [hash](http://php.net/manual/en/book.hash.php)
- One of the following supported databases, and the PHP extension to use it:
    - SQLite 3.8.3 or newer
    - PostgreSQL 8.4 or newer
    - MySQL 8.0.1 or newer
    - MariaDB 10.2.2 or newer
- The ability to run background services on the server; service files currently exist for:
    - systemd
    - launchd
    - sysvinit

**FIXME:** The requirements listed are prospective and not representative of the actual requirements as of this writing. Currently only SQLite is supported, no Web server configuration has yet been written, and no background process yet exists, never mind service files to run it.

License
-------

Arsse is made available under the permissive MIT license.  See the LICENSE file included with the distribution or source code for exact legal text. Dependencies included in the distribution may be governed by other licenses.

Running tests
-------------

To run the test suite, you must have [Composer](https://getcomposer.org/) installed as well as the command-line PHP interpreter (this is normally required to use Composer). Port 8000 must also be available for use by the built-in PHP Web server.

``` sh
# first install dependencies
composer install
# run the tests
./tests/test
```

The example uses Unix syntax, but the test suite also runs in Windows. By default all tests are run; you can pass the same arguments to the test runner [as you would to PHPUnit](https://phpunit.de/manual/current/en/textui.html#textui.clioptions):

``` sh
./tests/test --testsuite "Configuration"
```
