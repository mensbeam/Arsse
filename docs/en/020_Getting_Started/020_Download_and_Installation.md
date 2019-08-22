[TOC]

# Downloading The Arse

The latest version of The Arsse can be downloaded [from our releases page](https://code.mensbeam.com/MensBeam/arsse/releases). The attachments named _arsse-x.x.x.tar.gz_ should be used rather than those marked "Source Code".

Installation from source code is also possible, but the release packages are recommended.

# Installation

At present installing The Arsse is largely a manual process. We hope to some day make this easier by integrating the software into commonly used package managers, but for now the below instructions should serve as a useful guide.

In order for The Arsse to function correctly, [its requirements](Requirements) must first be satisfied. The process of installing the required PHP extensions differs from one system to the next, but on Debian the following series of commands should do:

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

# Next steps

If using a database other than SQLite, you will likely want to [set it up](Database_Setup) before doing anything else.

In order for the various synchronization protocols to work, a Web server [must be configured](Web_Server_Configuration), and in order for The Arsse to serve users, those users [must be created](/en/Using_The_Arsse/Managing_Users).

You may also want to review the `config.defaults.php` file included in the download package and create [a configuration file](Configuration), though The Arsse can function even without using a configuration file.

Finally, The Arsse's [newsfeed refreshing service](/en/Using_The_Arsse/Keeping_Newsfeeds_Up_to_Date) needs to be installed in order for news to actually be fetched from the Internet.
