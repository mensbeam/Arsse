[TOC]

# Downloading The Arsse

Since version 0.10.0 pre-built Debian packages for The Arsse are available from the [OpenSUSE Build Service](https://build.opensuse.org/) (OBS) under the author's personal project repository. This is the preferred method for instaling the software and is the means documented below.

Generic release tarballs may also be downloaded [from our Web site](https://thearsse.com), and a Debian package built manually. Installing directly from the generic release tarball without producing a Debian package is not recommended as the Debian packages make the set-up process on Debian systems significantly simpler.

# Adding the repository

In order to install The Arsse, the OBS repository must first be configured along with its signing key:

```sh
# Add the key
wget -q -O - "https://download.opensuse.org/repositories/home:/JKingWeb/Debian_Unstable/Release.key" | gpg --dearmor | sudo tee "/usr/share/keyrings/arsse-obs-keyring.gpg" >/dev/null
# Add the repository
echo "deb [signed-by=/usr/share/keyrings/arsse-obs-keyring.gpg] https://download.opensuse.org/repositories/home:/JKingWeb/Debian_Unstable/ ." | sudo tee "/etc/apt/sources.list.d/arsse-obs.list" >/dev/null
# Update APT's database
sudo apt update -qq
```

Please note that the "Unstable" qualifier in the repository URL is a reference to Debian's "sid" release and is not a reflection on The Arsse's stability. The repository should be suitable for any Debian version or derivative which includes a sufficiently recent version of PHP.

# Installation

Once the OBS repository is configured, installing The Arsse is achieved with a single command:

```sh
sudo apt install arsse
```

During the installation process you will be prompted whether to allow `dbconfig-common` to configure The Arsse's database. The default `sqlite3` (SQLite) option is a good choice, but `pgsql` (PostgreSQL) and `mysql` (MySQL) are possible alternatives. If you wish to [use a database other than SQLite](Database_Setup/index), you should install it before installing The Arsse:

```sh
# Install PostgreSQL
sudo apt install postgresql php-pgsql
# Install MySQL
sudo apt install mysql-server php-mysql
# Install SQLite explicitly
sudo apt install php-sqlite3
```

If you wish to change the database backend after having installed The Arsse, running `dpkg-reconfigure` after installing the database server can be used to achieve this:

```sh
sudo dpkg-reconfigure arsse
```

After installation is complete The Arsse will be started automatically.

# Web server configuration

Sample configuration for both Nginx and Apache HTTP Server can be found in `/etc/arsse/nginx/` and `/etc/arsse/apache/`, respectively. The `example.conf` files are basic virtual host examples; the other files they include should normally be usable without modification, but may be modified if desired.

In order to use Apache HTTP Server the FastCGI proxy module must be enabled and the server restarted:

```sh
sudo a2enmod proxy proxy_fcgi
sudo systemctl restart apache2
```

No additional set-up is required for Nginx.

# Next steps

In order for The Arsse to serve users, those users [must be created](/en/Using_The_Arsse/Managing_Users).

You may also want to review the `config.defaults.php` file included in the download package and create [a configuration file](/en/Getting_Started/Configuration), though The Arsse can function even without using a configuration file.

# Upgrading

Upgrading The Arsse is done like any other package. Occasionally changes to Web server configuration have been required, such as when new protocols become supported; these changes are always explicit in the `UPGRADING` file.
