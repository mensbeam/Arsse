Name:           arsse
Version:        0.10.0
Release:        0
Summary:        Multi-protocol RSS/Atom newsfeed synchronization server
License:        MIT
Group:          Productivity/Networking/Web/Utilities
URL:            https://thearsse.com/
Source0:        %{name}-%{version}.tar.gz
BuildArch:      noarch

%define minver 7.1

Requires:       php >= %{minver}
Requires:       php-intl
Requires:       php-dom
Requires:       php-simplexml
Requires:       php-iconv
Requires:       php-posix
Requires:       php-pcntl
# This is usually compiled in
Requires:       php-filter
# The below extensions are part of the PHP core in recent versions
Requires:       php-hash
Requires:       php-json
# A database option is required; a Web server option is required as well, but what we package is not exhaustive
Requires:       arsse-conf-db

Recommends:     arsse-sqlite
Suggests:       php-curl

Provides:       arsse = %{version}
Obsoletes:      arsse < %{version}

BuildRequires:  systemd-rpm-macros

%description
The Arsse bridges the gap between multiple existing newsfeed aggregator
client protocols such as Tiny Tiny RSS, Nextcloud News and Miniflux,
allowing you to use compatible clients for many protocols with a single
server.

%package sqlite
Summary:        SQLite database configuration for The Arsse
Group:          Productivity/Networking/Web/Utilities
Requires:       (php-sqlite or php-pdo_sqlite)
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-pgsql
Conflicts:      arsse-mysql
Provides:       arsse-config-db
Provides:       arsse-sqlite = %{version}
Obsoletes:      arsse-sqlite < %{version}

%package pgsql
Summary:        PostgreSQL database configuration for The Arsse
Group:          Productivity/Networking/Web/Utilities
Requires:       (php-pgsql or php-pdo_pgsql)
Requires:       postgresql-server >= 10
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-sqlite
Conflicts:      arsse-mysql
Provides:       arsse-config-db
Provides:       arsse-pgsql = %{version}
Obsoletes:      arsse-pgsql < %{version}

%package mysql
Summary:        MySQL database configuration for The Arsse
Group:          Productivity/Networking/Web/Utilities
Requires:       (php-mysql or php-pdo_mysql)
Requires:       mysql-server >= 8.0
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-sqlite
Conflicts:      arsse-pgsql
# OpenSUSE only packages MariaDb, which does not worth with The Arsse
#Provides:      arsse-config-db
Provides:       arsse-mysql = %{version}
Obsoletes:      arsse-mysql < %{version}

%package config-nginx-fpm
Summary:        Nginx Web server configuration for The Arsse using PHP-FPM
Group:          Productivity/Networking/Web/Utilities
Requires:       php-fpm >= %{minver}
Requires:       nginx
Requires:       %{name} = %{version}-%{release}
Provides:       arsse-nginx-fpm = %{version}
Obsoletes:      arsse-nginx-fpm < %{version}
Supplements:    packageand(apache2:arsse)

%package config-apache-fpm
Summary:        Apache Web server configuration for The Arsse using PHP-FPM
Group:          Productivity/Networking/Web/Utilities
Requires:       php-fpm >= %{minver}
Requires:       %{name} = %{version}-%{release}
Requires:       apache >= 2.4
Provides:       arsse-apache-fpm = %{version}
Obsoletes:      arsse-apache-fpm < %{version}
Supplements:    packageand(apache2:arsse)

%prep
%setup -q -n %{name}
### Perform adjustments to config files here?

%build
# Nothing to do

%install
cp -r lib locale sql vendor www CHANGELOG UPGRADING README.md arsse.php "%{buildroot}/usr/share/php/arsse"
cp -r manual/* "%{buildroot}/usr/share/doc/arsse"
