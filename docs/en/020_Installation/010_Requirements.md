The Arsse has the following requirements:

- A Linux server utilizing systemd and Nginx (tested on Ubuntu 16.04)
- PHP 7.0.7 or later with the following extensions:
    - [intl](http://php.net/manual/en/book.intl.php), [json](http://php.net/manual/en/book.json.php), and [hash](http://php.net/manual/en/book.hash.php)
    - [dom](http://php.net/manual/en/book.dom.php), [simplexml](http://php.net/manual/en/book.simplexml.php), and [iconv](http://php.net/manual/en/book.iconv.php) (for picoFeed)
    - One of:
        - [sqlite3](http://php.net/manual/en/book.sqlite3.php) or [pdo_sqlite](http://php.net/manual/en/ref.pdo-sqlite.php) for SQLite databases
        - [pgsql](http://php.net/manual/en/book.pgsql.php) or [pdo_pgsql](http://php.net/manual/en/ref.pdo-pgsql.php) for PostgreSQL 10 or later databases
        - [mysqli](http://php.net/manual/en/book.mysqli.php) or [pdo_mysql](http://php.net/manual/en/ref.pdo-mysql.php) for MySQL 8.0.7 or later databases
- Privileges to create and run daemon processes on the server
