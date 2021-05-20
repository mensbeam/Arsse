[TOC]

# Downloading The Arsse

The latest version of The Arsse can be downloaded [from our Web site](https://thearsse.com/). If installing an older release from our archives, the attachments named _arsse-x.x.x.tar.gz_ should be used rather than those marked "Source Code".

Installation from source code is also possible, but the release packages are recommended.

# Installation

In order for The Arsse to function correctly, its requirements must first be satisfied. The following series of commands should do so:

```sh
# Install PHP; this assumes the FastCGI process manager will be used
sudo apt install php-cli php-fpm
# Install the needed PHP extensions; php-curl is optional
sudo apt install php-intl php-json php-xml php-curl
# Install any one of the required database extensions
sudo apt install php-sqlite3 php-pgsql php-mysql
```

Then, it's a simple matter of unpacking the archive someplace (`/usr/share/arsse` is the recommended location on Debian systems, but it can be anywhere) and setting permissions:

```sh
# Unpack the archive
sudo tar -xzf arsse-x.x.x.tar.gz -C "/usr/share"
# Make the user running the Web server the owner of the files
sudo chown -R www-data:www-data "/usr/share/arsse"
# Ensure the owner can create files such as the SQLite database
sudo chmod o+rwX "/usr/share/arsse"
```

# Web server configuration

Sample configuration for both Nginx and Apache HTTPd can be found in `dist/nginx/` and `dist/apache/`, respectively. The `example.conf` files are basic virtual host examples; the other files they include should normally be usable without modification, but may be modified if desired.

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

Finally, The Arsse's [newsfeed refreshing service](/en/Using_The_Arsse/Keeping_Newsfeeds_Up_to_Date) needs to be installed in order for news to actually be fetched from the Internet.
