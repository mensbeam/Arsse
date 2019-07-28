# About

<dl>
    <dt>Supported since</dt>
        <dd>0.6.0</dd>
    <dt>dbDriver identifier</dt>
        <dd>mysql</dd>
    <dt>Minimum version</dt>
        <dd>8.0.11</dd>
    <dt>Configuration</dt>
        <dd><a href="/en/Configuring_The_Arsse#page_Database-settings">General</a>, <a href="/en/Configuring_The_Arsse#page_Database-settings-specific-to-MySQL">Specific</a></dd>
</dl>

While MySQL can be used as a database for The Arsse, this is **not recommended** due to MySQL's technical limitations. It is fully functional, but may fail with some newsfeeds where other database systems do not. Additionally, it is particularly important before upgrading from one version of The Arsse to the next to back up your database: a failure in a database upgrade can corrupt your database much more easily than when using other database systems.

Please note that as of this writing MariaDB cannot be used in place of MySQL as it lacks features of MySQL 8 which The Arsse requires. The awkwardly-named _Percona Server for MySQL_, on the other hand, should work, though this has not been tested.

# Set-up

In order to use a MySQL database for The Arsse, the database must already exist. The procedure for creating a database can differ between systems, but a typical Linux procedure is as follows:

```sh
sudo mysql -e "CREATE USER 'arsseuser'@'localhost' IDENTIFIED BY 'super secret password'"
sudo mysql -e "CREATE DATABASE arssedb"
sudo mysql -e "GRANT ALL ON arssedb.* TO 'arsseuser'@'localhost'"
```

Tha Arsse must then be configured to use the created database. A suitable [configuration file](/en/Configuring_The_Arsse) might look like this:

```php
<?php
return [
    'dbDriver'    => "mysql",
    'dbMySQLUser' => "arsseuser",
    'dbMySQLPass' => "super secret password",
    'dbMySQLDb'   => "arssedb",
];
```

Numerous alternate configurations are possible; the above is merely the simplest.
