_[TOC]

# Downloading The Arsse

The Arsse is available from the [Arch User Repository](https://aur.archlinux.org/) as packages `arsse` and `arsse-git`. The latter should normally only be used to test bug fixes.

Generic release tarballs may also be downloaded [from our Web site](https://thearsse.com). The `PKGBUILD` file (found under `arsse/dist/arch/`) can then be extracted alongside the tarball and used to build the `arsse` package.

# Installation

For illustrative purposes, this document assumes the `yay` [AUR helper](https://wiki.archlinux.org/title/AUR_helpers) will be used to download, build, and install The Arsse. This section summarises the steps necessary to configure and use The Arsse after installtion:

```sh
# Install the package
sudo yay -S arsse
# Enable the necessary PHP extensions; curl is optional but recommended; pdo_sqlite may be used instead of sqlite, but this is not recommended
sudo sed -ie 's/^;\(extension=\(curl\|iconv\|intl\|sqlite3\)\)$/\1/' /etc/php/php.ini
# Enable the necessary systemd units
sudo systemctl enable php-fpm arsse
sudo systemctl restart php-fpm arsse
```

Note that the above is the most concise process, not necessarily the recommended one. In particular [it is recommended](https://wiki.archlinux.org/title/PHP#Extensions) to use `/etc/php/conf.d/` to enable extensions rather than editing `php.ini` as done above. 

# Next steps

If using a database other than SQLite, you will likely want to [set it up](Database_Setup) before doing anything else.

In order for the various synchronization protocols to work, a Web server [must be configured](Web_Server_Configuration), and in order for The Arsse to serve users, those users [must be created](/en/Using_The_Arsse/Managing_Users).

You may also want to review the `config.defaults.php` file included in the download package and create [a configuration file](Configuration), though The Arsse can function even without using a configuration file.