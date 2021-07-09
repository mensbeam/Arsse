[TOC]

# Downloading The Arsse

Since version 0.9.2 The Arsse is available from the [Arch User Repository](https://aur.archlinux.org/) as packages `arsse` and `arsse-git`. The latter should normally only be used to test bug fixes.

Generic release tarballs may also be downloaded [from our Web site](https://thearsse.com), and the `PKGBUILD` file (found under `arsse/dist/arch/`) can then be extracted alongside the tarball and used to build the `arsse` package. Installing directly from the generic release tarball without producing an Arch package is not recommended as the package-building process performs various adjustments to handle Arch peculiarities.

# Installation

For illustrative purposes, this document assumes the `yay` [AUR helper](https://wiki.archlinux.org/title/AUR_helpers) will be used to download, build, and install The Arsse. This section summarises the steps necessary to configure and use The Arsse after installtion:

```sh
# Install the package
sudo yay -S arsse
# Enable the necessary PHP extensions; curl is optional but recommended; pdo_sqlite may be used instead of sqlite3, but this is not recommended
sudo sed -ie 's/^;\(extension=\(curl\|iconv\|intl\|sqlite3\)\)$/\1/' /etc/php/php.ini
# Enable and start the necessary systemd units
sudo systemctl enable php-fpm arsse
sudo systemctl restart php-fpm arsse
```

Note that the above is the most concise process, not necessarily the recommended one. In particular [it is recommended](https://wiki.archlinux.org/title/PHP#Extensions) to use `/etc/php/conf.d/` to enable PHP extensions rather than editing `php.ini` as done above.

The PHP extensions listed in [the requirements](/en/Getting_Started/index) not mentioned above are compiled into Arch's PHP binaries and thus always enabled.

# Web server configuration

Sample configuration for both Nginx and Apache HTTP Server can be found in `/etc/webapps/arsse/nginx/` and `/etc/webapps/arsse/apache/`, respectively. The `example.conf` files are basic virtual host examples; the other files they include should normally be usable without modification, but may be modified if desired.

If using Apache HTTP Server the `mod_proxy` and `mod_proxy_fcgi` modules must be enabled. This can be achieved by adding the following lines to your virtual host or global configuration:

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
```

No additional set-up is required for Nginx.

# Next steps

If using a database other than SQLite, you will likely want to [set it up](/en/Getting_Started/Database_Setup) before doing anything else.

In order for The Arsse to serve users, those users [must be created](/en/Using_The_Arsse/Managing_Users).

You may also want to review the `config.defaults.php` file included in `/etc/webapps/arsse/` or consult [the documentation for the configuration file](/en/Getting_Started/Configuration), though The Arsse should function with the default configuration.

# Upgrading

Upgrading The Arsse is done like any other package. By default The Arsse will perform any required database schema upgrades when the new version is executed, so the service does need to be restarted:

```sh
sudo systemctl restart arsse
```

Occasionally changes to Web server configuration have been required, such as when new protocols become supported; these changes are always explicit in the `UPGRADING` file.
