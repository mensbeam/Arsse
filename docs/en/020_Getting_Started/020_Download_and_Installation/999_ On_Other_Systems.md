# Downloading The Arsse

The Arsse should run on any operating system for which PHP and a Web server are available, but only the combination of Linux, Systemd, Nginx, and PHP-FPM has been extensively tested.

Below are very generic instructions and suggestions for installing The Arsse on systems for which pre-built packages are not available.

# Requirements

The Arsse has the following requirements:

- A Web server such as:
    - [Nginx](https://nginx.org)
    - [Apache HTTP server](https://httpd.apache.org) 2.4 or later
- PHP 7.1.0 or later with the following extensions:
    - [intl](https://php.net/manual/en/book.intl.php), [json](https://php.net/manual/en/book.json.php), [hash](https://php.net/manual/en/book.hash.php), [filter](https://php.net/manual/en/book.filter.php), and [dom](https://php.net/manual/en/book.dom.php)
    - [simplexml](https://php.net/manual/en/book.simplexml.php), and [iconv](https://php.net/manual/en/book.iconv.php)
    - One of:
        - [sqlite3](https://php.net/manual/en/book.sqlite3.php) or [pdo_sqlite](https://php.net/manual/en/ref.pdo-sqlite.php) for SQLite databases
        - [pgsql](https://php.net/manual/en/book.pgsql.php) or [pdo_pgsql](https://php.net/manual/en/ref.pdo-pgsql.php) for PostgreSQL 10 or later databases
        - [mysqli](https://php.net/manual/en/book.mysqli.php) or [pdo_mysql](https://php.net/manual/en/ref.pdo-mysql.php) for MySQL/Percona 8.0.11 or later databases
    - [curl](https://php.net/manual/en/book.curl.php) (optional)
    - [posix](https://php.net/manual/en/book.posix.php) and [pcntl](https://php.net/manual/en/book.pcntl.php) (both optional)
- An interface between PHP and the Web server, such as [PHP-FPM](https://php.net/manual/en/install.fpm.php)
- Privileges either to create and run system services, or to run cron jobs

# Installation

1. Download [the latest release](https://thearsse.com/releases/current) and extract it somewhere, such as `/usr/share/arsse/`
2. [Set up your database](/en/Getting_Started/Database_Setup)
3. Create [a configuration file](/en/Getting_Started/Configuration) if needed
4. Consult the files under `dist/nginx` and `dist/apache` for sample Web server configuration
5. Consult `dist/arsse` for a sample executable script which drops privileges on POSIX systems
6. Start the newsfeed fetching service:
    - Sample Systemd service files are available under `dist/systemd`
    - A sample System V init script is available in `dist/init.sh`
    - A persistent process can be started by running `php arsse.php daemon`
    - It is also possible [to use cron](/en/Using_The_Arsse/Other_Topics.html#page_Refreshing_newsfeeds_with_a_cron_job) or a similar task-scheduling tool
7. [Create users](/en/Using_The_Arsse/Managing_Users) to grant them access

# Upgrading

Upgrading The Arsse is usually simple:

1. Download the latest release
2. Check the `UPGRADING` file for any special notes
3. Stop the newsfeed refreshing service if it is running
4. Back up your configurationm and database
5. Extract the new version on top of the old one
6. Restart the newsfeed refreshing service

By default The Arsse will perform any required database schema upgrades when the new version is executed.

Occasionally changes to Web server configuration have been required, when new protocols become supported; such changes are always explicit in the `UPGRADING` file
