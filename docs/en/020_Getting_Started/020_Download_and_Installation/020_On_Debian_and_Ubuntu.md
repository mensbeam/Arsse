[TOC]

# Downloading The Arsse

The latest version of The Arsse can be downloaded [from our Web site](https://thearsse.com/). If installing an older release from our archives, the attachments named _arsse-x.x.x.tar.gz_ should be used rather than those marked "Source Code".

Installation from source code is also possible, but the release packages are recommended.

# Installation

Presently installing The Arsse on Debian systems is a manual process. The first step is to install its dependencies:

```sh
# Install PHP; this assumes the FastCGI process manager will be used
sudo apt install php-cli php-fpm
# Install the needed PHP extensions; php-curl is optional
sudo apt install php-intl php-json php-xml php-curl
# Install any one of the required database extensions
sudo apt install php-sqlite3 php-pgsql php-mysql
```

Next its files must be unpacked into their requisite locations:

```sh
# Unpack the archive
sudo tar -xzf arsse-x.x.x.tar.gz -C "/usr/share"
# Create necessary directories
sudo mkdir -p /etc/arsse /etc/sysusers.d /etc/tmpfiles.d
# Find the PHP version
php_ver=`phpquery -V`
# Move configuration files to their proper locations
cd /usr/share/arsse/dist
sudo mv systemd/* /etc/systemd/system/
sudo mv sysusers.conf /etc/sysusers.d/arsse.conf
sudo mv tmpfiles.conf /etc/tmpfiles.d/arsse.conf
sudo mv config.php nginx apache /etc/arsse/
sudo mv php-fpm.conf /etc/php/$php_ver/fpm/pool.d/arsse.conf
# Move the administration executable
sudo mv arsse /usr/bin/
```

Finally, services must be restarted to apply the new configurations, and The Arsse's service also started:

```sh
sudo systemctl restart systemd-sysusers
sudo systemd-tmpfiles --create
sudo systemctl restart php$php_ver-fpm
sudo systemctl reenable arsse
sudo systemctl restart arsse
```

# Web server configuration

Sample configuration for both Nginx and Apache HTTPd can be found in `/etc/arsse/nginx/` and `/etc/arsse/apache/`, respectively. The `example.conf` files are basic virtual host examples; the other files they include should normally be usable without modification, but may be modified if desired.

In order to use Apache HTTPd the FastCGI proxy module must be enabled and the server restarted:

```sh
sudo a2enmod proxy proxy_fcgi
sudo systemctl restart apache2
```

No additional set-up is required for Nginx.

# Next steps

If using a database other than SQLite, you will likely want to [set it up](/en/Getting_Started/Database_Setup) before doing anything else.

In order for The Arsse to serve users, those users [must be created](/en/Using_The_Arsse/Managing_Users).

You may also want to review the `config.defaults.php` file included in the download package and create [a configuration file](/en/Getting_Started/Configuration), though The Arsse can function even without using a configuration file.

# Upgrading

Upgrading The Arsse is simple:

1. Download the latest release
2. Check the `UPGRADING` file for any special notes
3. Stop the newsfeed refreshing service if it is running
4. Install the new version per the process above
6. Start the newsfeed refreshing service

By default The Arsse will perform any required database schema upgrades when the new version is executed. Occasionally changes to Web server configuration have been required, such as when new protocols become supported; these changes are always explicit in the `UPGRADING` file.
