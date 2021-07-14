Name: arsse
Version: 0.10.0
Release: 0
Summary: Multi-protocol RSS/Atom newsfeed synchronization server
License: MIT
Group: Productivity/Networking/Web/Utilities
URL: https://thearsse.com/
Source0: %{name}-%{version}.tar.gz
BuildArch: noarch

Requires: php >= 7.1
Requires: php-intl
Requires: php-dom
Requires: php-simplexml
Requires: php-iconv
Requires: php-posix
Requires: php-pcntl
# This is usually compiled in
Requires: php-filter
# The below extensions are part of the PHP core in recent versions
Requires: php-hash
Requires: php-json
# A Web server option and database option are required
Requires: arsse-www-conf
Requires: arsse-db-conf

Recommends: arsse-sqlite
Recommends: arsse-nginx-fpm

%description
The Arsse bridges the gap between multiple existing newsfeed aggregator
client protocols such as Tiny Tiny RSS, Nextcloud News and Miniflux,
allowing you to use compatible clients for many protocols with a single
server.

%package sqlite
Requires: (php-sqlite or php-pdo_sqlite)
