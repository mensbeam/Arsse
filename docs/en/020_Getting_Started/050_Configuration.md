# The Configuration File

The Arsse looks for configuration in a file named `config.php` in the directory where it is installed. For example, if The Arsse is installed at `/usr/share/arsse`, it will look for configuration in the file `/usr/share/arsse/config.php`. It is not an error for this file not to exist or to be empty: The Arsse will function with no configuration whatsoever, provided other conditions allow.

The configuration file is a PHP script which returns an associative array with keys and values for one or more settings. Any settings which are not specified in the configuration file will be set to their defaults. Invalid values will cause an error on start-up, while unknown keys are ignored. A basic configuration file might look like this:

```php
<?php return [
    'lang'          => "en",
    'dbDriver'      => "sqlite3",
    'dbSQLite3File' => "/var/lib/arsse/arsse.db",
];
```

The `config.defaults.php` file included with copies of The Arsse contains an annotated listing of every configuration setting with its default value. The settings are also documented in more detail below.

# List of All Settings

## General settings

### lang

| Type   | Default |
|--------|---------|
| string | `"en"`  |

The default language locale, mostly used when reporting errors on the command line or in logs. Currently only `"en"` (English) is available.

## Database settings

### dbDriver

| Type   | Default     |
|--------|-------------|
| string | `"sqlite3"` |

The database driver to use. The following values are understood:

- `"sqlite3"` for SQLite databases
- `"postgresql"` for PostgreSQL databases
- `"mysql"` for MySQL databases

It is also possible to specify the fully-qualified name of a class which implements the database driver interface. For example, specifying `"JKingWeb\Arsse\Db\SQLite3\PDODriver"` would use the PDO driver for SQLite 3.

### dbAutoUpdate

| Type    | Default |
|---------|---------|
| boolean | `true`  |

Whether to attempt to automatically upgrade the database schema when upgrading The Arsse to a new version with schema changes.

If set to `false`, the database schema must be manually upgraded. Schema files can be found under `sql/<backend>/#.sql`; the `UPGRADING` file will advise when a schema upgrade is required.

### dbTimeoutConnect

| Type             | Default |
|------------------|---------|
| interval or null | `5.0`   |

The number of seconds to wait before returning a timeout error when connecting to a database server. The special value `null` waits the maximum amount of time, while `0.0` waits the minimum amount of time. The minimums are maximums for each backend are as follows:

| Backend    | Minimum  | Maximum  |
|------------|----------|----------|
| SQLite 3   | *(does not apply)*  | *(does not apply)* |
| PostgreSQL | 1 second | forever  |
| MySQL      | 1 second | forever  |

Note that in practice neither PostgreSQL nor MySQL will wait indefinitely: they are still subject to PHP's socket timeouts. Consult [PostgreSQL's documentation](https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-CONNECT-TIMEOUT) for details on how the timeout is interpreted by PostgreSQL. 

### dbTimeoutExec

| Type     | Default |
|----------|---------|
| interval | `null`  |

The number of seconds to wait before returning a timeout error when executing a database operation (i.e. computing results). The special value `null` waits the maximum amount of time, while `0.0` waits the minimum amount of time. The minimums are maximums for each backend are as follows:

| Backend    | Minimum       | Maximum  |
|------------|---------------|----------|
| SQLite 3   | *(does not apply)*       | *(does not apply)* |
| PostgreSQL | 1 millisecond | forever  |
| MySQL      | 1 second      | forever  |

With MySQL this timeout only applies to read operations, whereas PostgreSQL will time out write operations as well. 

### dbTimeoutLock

| Type     | Default |
|----------|---------|
| interval | `60.0`  |

The number of seconds to wait before returning a timeout error when acquiring database locks. The special value `null` waits the maximum amount of time, while `0.0` waits the minimum amount of time. The minimums are maximums for each backend are as follows:

| Backend    | Minimum        | Maximum               |
|------------|----------------|-----------------------|
| SQLite 3   | 0 milliseconds | at least 24 days      |
| PostgreSQL | 1 millisecond  | forever               |
| MySQL      | 1 second       | approximately 1 year  |

Note that PostgreSQL counts time spent waiting for locks as part of the above execution timeout. The maximum timeout for SQLite is `PHP_INT_MAX` milliseconds, which on 32-bit systems is just under 25 days, and on 64-bit systems is billions of years.

## Database settings specific to SQLite 3

### dbSQLite3File

| Type           | Default |
|----------------|---------|
| string or null | `null`  |

The full path and file name of SQLite database. The special value `null` evaluates to a file named `"arsse.db"` in the directory where The Arsse is installed.

### dbSQLite3Key

| Type   | Default |
|--------|---------|
| string | `""`    |

The key used to encrypt/decrypt the SQLite database. This is only relevant if using the [SQLite Encryption Extension](https://www.sqlite.org/see/).

## Database settings specific to PostgreSQL

### dbPostgreSQLHost

| Type   | Default |
|--------|---------|
| string | `""`    |

The host name, address, or socket path of the PostgreSQL database server.

Consult [PostgreSQL's documentation](https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-CONNECT-HOST) for more details.

### dbPostgreSQLUser

| Type   | Default   |
|--------|-----------|
| string | `"arsse"` |

The log-in user name for the PostgreSQL database server.

### dbPostgreSQLPass

| Type   | Default |
|--------|---------|
| string | `""`    |

The log-in password for the PostgreSQL database server.

### dbPostgreSQLPort

| Type    | Default |
|---------|---------|
| integer | `5432`  |

The TCP port on which to connect to the PostgreSQL database server, if connecting via TCP.

### dbPostgreSQLDb

| Type   | Default   |
|--------|-----------|
| string | `"arsse"` |

The name of the database used by The Arsse on the PostgreSQL database server.

### dbPostgreSQLSchema

| Type   | Default |
|--------|---------|
| string | `""`    |

The name of the schema used by The Arsse on the PostgreSQL database server. When not set to the empty string, the PostgreSQL search path is modified to consist of the specified schema with a fallback to the public schema.

Consult [PostgreSQL's documentation](https://www.postgresql.org/docs/current/ddl-schemas.html) for more details.

### dbPostgreSQLService

| Type   | Default |
|--------|---------|
| string | `""`    |

A PostgreSQL service file entry to use *instead of* the above configuration; if using a service entry all above PostgreSQL-specific parameters except schema are ignored.

Consult [PostgreSQL's documentation](https://www.postgresql.org/docs/current/libpq-pgservice.html) for more details.

## Database settings specific to MySQL

### dbMySQLHost

| Type   | Default       |
|--------|---------------|
| string | `"localhost"` |

The host name or address of the MySQL database server. The values `"localhost"` and `"."` are special.

Consult [MySQL's documentation](https://dev.mysql.com/doc/refman/8.0/en/connecting.html) for more details.

### dbMySQLUser

| Type   | Default   |
|--------|-----------|
| string | `"arsse"` |

The log-in user name for the MySQL database server.

### dbMySQLPass

| Type   | Default |
|--------|---------|
| string | `""`    |

The log-in password for the MySQL database server.

### dbMySQLPort

| Type    | Default |
|---------|---------|
| integer | `3306`  |

The TCP port on which to connect to the MySQL database server, if connecting via TCP.

### dbMySQLDb

| Type   | Default   |
|--------|-----------|
| string | `"arsse"` |

The name of the database used by The Arsse on the MySQL database server.

### dbMySQLSocket

| Type   | Default |
|--------|---------|
| string | `""`    |

A Unix domain socket or named pipe to use for the MySQL database server when not connecting via TCP.

## User management settings

### userDriver

| Type   | Default      |
|--------|--------------|
| string | `"internal"` |

The user management driver to use. Currently only `"internal"` is available, which stores user IDs and hashed passwords in the configured database.

It is also possible to specify the fully-qualified name of a class which implements the user management driver interface. For example, specifying `"JKingWeb\Arsse\User\Internal\Driver"` would use the internal driver.

### userPreAuth

| Type    | Default |
|---------|---------|
| boolean | `false` |

Whether users are authenticated by the Web server before requests are passed to The Arsse. If set to `true` The Arsse will perform no HTTP-level authentication and assume that the user ID supplied in either the `REMOTE_USER` CGI variable or the `Authorization` HTTP header-field (if `Basic` authentication was used) is authentic.  

For synchronization protocols which implement their own authentication (such as Tiny Tiny RSS), this setting may or may not affect how protocol-level authentication is handled; consult the section on The Arsse's [supported protocols](/en/Supported_Protocols) for more information.

If the user has not previously logged in, an entry is created for them in the database automatically. If the Web server uses `Basic` HTTP authentication and passes along the `Authorization` field, a hash of the user's password will also be stored in The Arsse's database.

### userHTTPAuthRequired

| Type    | Default |
|---------|---------|
| boolean | `false` |

Whether to require successful HTTP authentication before processing API-level authentication, for protocol which implement their own authentication.

### userSessionEnforced

| Type    | Default |
|---------|---------|
| boolean | `true`  |

Whether invalid or expired API session tokens should prevent logging in when HTTP authentication is used, for protocol which implement their own authentication.

### userSessionTimeout

| Type     | Default   |
|----------|-----------|
| interval | `"PT24H"` |

The period of inactivity after which log-in sessions should be considered invalid. Session timeouts should not be made too long, to guard against session hijacking.

### userSessionLifetime

| Type     | Default   |
|----------|-----------|
| interval | `"P7D"`   |

The maximum lifetime of log-in sessions regardless of recent activity. Session lifetimes should not be made too long, to guard against session hijacking.

### userTempPasswordLength

| Type    | Default |
|---------|---------|
| integer | `20`    |

The desired length in characters of randomly-generated user passwords. When [adding users](/en/Using_The_Arsse/Managing_Users), omitting a desired password generates a random one; this setting controls the length of these passwords.

## Newsfeed fetching service settings

### serviceDriver

| Type   | Default        |
|--------|----------------|
| string | `"subprocess"` |

The newsfeed fetching service driver to use. The following values are understood:

- `"serial"`: Fetches newsfeeds and processed them one at a time. This is the slowest method, but is simple and reliable.
- `"subprocess"`: Fetches and processes multiple newsfeeds concurrently by starting a separate process for each newsfeed using PHP's [`popen`](https://php.net/manual/en/function.popen.php) function. This uses more memory and processing power, but takes less total time.

It is also possible to specify the fully-qualified name of a class which implements the service driver interface. For example, specifying `"JKingWeb\Arsse\Service\Serial\Driver"` would use the serial driver.

### serviceFrequency

| Type     | Default  |
|----------|----------|
| interval | `"PT2M"` |

The interval the newsfeed fetching service observes between checks for new articles. Note that requests to foreign servers are not necessarily made at this frequency: each newsfeed is assigned its own time at which to be next retrieved. This setting instead defines the length of time the fetching service will sleep between periods of activity.

Consult "[How Often Newsfeeds Are Fetched](/en/Using_The_Arsse/Keeping_Newsfeeds_Up_to_Date#page_Appendix-How-Often-Newsfeeds-Are-Fetched)" for details on how often newsfeeds are fetched.

### serviceQueueWidth

| Type    | Default |
|---------|---------|
| integer | `5`     |

The maximum number of concurrent newsfeed updates to perform, if a concurrent service driver is used. 

### fetchTimeout

| Type     | Default |
|----------|---------|
| interval | `10.0`  |

The maximum number of seconds to wait for data when fetching newsfeeds from foreign servers.

### fetchSizeLimit

| Type    | Default           |
|---------|-------------------|
| integer | `2 * 1024 * 1024` |

The maximum size, in bytes, of data to accept when fetching a newsfeed. Newsfeeds larger than this will be rejected to guard against denial-of-servioce attacks.

The default value is equal to two megabytes.

### fetchEnableScraping

| Type    | Default |
|---------|---------|
| boolean | `true`  |

Whether to allow the possibility of fetching full article contents from an article's source, if a newsfeed only provides excerpts. Whether fetching will actually happen is governed by a per-newsfeed toggle (defaulting to `false`) which currently can only be changed by manually editing the database.

### fetchUserAgentString

| Type           | Default |
|----------------|---------|
| string or null | `null`  |

The [user agent](https://tools.ietf.org/html/rfc7231#section-5.5.3) The Arsse will identify as when fetching newsfeeds. The special value null will use an identifier similar to the following:

```
Arsse/0.6.0 (Linux 4.15.0; x86_64; https://thearsse.com/)
```

## Housekeeping settings

### purgeFeeds

| Type             | Default   |
|------------------|-----------|
| interval or null | `"PT24H"` |

How long to keep a newsfeed and its articles in the database after all its subscriptions have been deleted. Specifying `null` will retain unsubscribed newsfeeds forever, whereas an interval evaluating to zero (e.g. `"PT0S"`) will delete them immediately.

Note that articles of orphaned newsfeeds are still subject to the `purgeArticleUnread` threshold below.

### purgeArticlesRead

| Type             | Default |
|------------------|---------|
| interval or null | `"P7D"` |

How long to keep a an article in the database after all users subscribed to its newsfeed have read it. Specifying `null` will retain articles up to the `purgeArticlesUnread` threshold below, whereas an interval evaluating to zero (e.g. `"PT0S"`) will delete them immediately. 

If an article is starred by any user, it is retained indefinitely regardless of this setting.

This setting also governs when an article is hidden from a user after being read by that user, regardless of its actual presence in the database.

### purgeArticlesUnread

| Type             | Default  |
|------------------|----------|
| interval or null | `"P21D"` |

How long to keep a an article in the database regardless of whether any users have read it. Specifying `null` will retain articles forever, whereas an interval evaluating to zero (e.g. `"PT0S"`) will delete them immediately. 

If an article is starred by any user, it is retained indefinitely regardless of this setting.

# Obsolete settings

### dbSQLite3Timeout

| Type     | Historical Default |
|----------|--------------------|
| interval | `60.0`             |

*This setting has been replaced by [dbTimeoutLock](#page_dbTimeoutLock).*

The number of seconds for SQLite to wait before returning a timeout error when trying to acquire a write lock on the database file. Setting this to a low value may cause operations to fail with an error.

Consult [SQLite's documentation](https://sqlite.org/c3ref/busy_timeout.html) for more details.
