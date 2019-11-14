# About

<dl>
    <dt>Supported since</dt>
        <dd>0.6.0</dd>
    <dt>dbDriver identifier</dt>
        <dd>postgresql</dd>
    <dt>Minimum version</dt>
        <dd>10</dd>
    <dt>Configuration</dt>
        <dd><a href="../Configuration.html#page_Database-settings">General</a>, <a href="../Configuration.html#page_Database-settings-specific-to-PostgreSQL">Specific</a></dd>
</dl>

If for whatever reason an SQLite database does not suit your configuration, PostgreSQL is the best alternative. It is functionally equivalent to SQLite in every way.

# Set-up

In order to use a PostgreSQL database for The Arsse, the database must already exist. The procedure for creating a database can differ between systems, but a typical Linux procedure is as follows:

```sh
sudo -u postgres psql -c "CREATE USER arsseuser WITH PASSWORD 'super secret password'"
sudo -u postgres psql -c "CREATE DATABASE arssedb WITH OWNER arsseuser"
```

The Arsse must then be configured to use the created database. A suitable [configuration file](/en/Getting_Started/Configuration) might look like this:

```php
<?php
return [
    'dbDriver'         => "postgresql",
    'dbPostgreSQLUser' => "arsseuser",
    'dbPostgreSQLPass' => "super secret password",
    'dbPostgreSQLDb'   => "arssedb",
];
```

Numerous alternate configurations are possible; the above is merely the simplest.
