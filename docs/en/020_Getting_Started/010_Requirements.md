The Arsse has the following requirements:

- A Linux server running Nginx or Apache 2.4 (tested on Ubuntu 16.04 and 18.04)
- PHP 7.0.7 or later with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php), [json](http://php.net/manual/en/book.json.php), [hash](http://php.net/manual/en/book.hash.php), and [dom](http://php.net/manual/en/book.dom.php)
    - [simplexml](http://php.net/manual/en/book.simplexml.php), and [iconv](http://php.net/manual/en/book.iconv.php)
    - One of:
        - [sqlite3](http://php.net/manual/en/book.sqlite3.php) or [pdo_sqlite](http://php.net/manual/en/ref.pdo-sqlite.php) for SQLite databases
        - [pgsql](http://php.net/manual/en/book.pgsql.php) or [pdo_pgsql](http://php.net/manual/en/ref.pdo-pgsql.php) for PostgreSQL 10 or later databases
        - [mysqli](http://php.net/manual/en/book.mysqli.php) or [pdo_mysql](http://php.net/manual/en/ref.pdo-mysql.php) for MySQL/Percona 8.0.11 or later databases
- Privileges either to create and run systemd services, or to run cron jobs

Instructions for how to satisfy the PHP extension requirements for Debian systems are included in the next section.

It is also be possible to run The Arsse on other operating systems (including Windows) and with other Web servers, but the configuration required to do so is not documented in this manual.
