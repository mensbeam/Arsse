Name:           arsse
Version:        0.10.0
Release:        0
Summary:        Multi-protocol RSS/Atom newsfeed synchronization server
License:        MIT
Group:          Productivity/Networking/Web/Utilities
URL:            https://thearsse.com/
Source0:        %{name}-%{version}.tar.gz
BuildArch:      noarch

%define phpver 7.1

Requires:       php >= %{phpver}
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
# A Web server option and database option are required
Requires:       arsse-conf-www
Requires:       arsse-conf-db

Recommends:     arsse-sqlite
Recommends:     arsse-nginx-fpm
Suggests:       php-curl

Provides:       arsse = %{version}
Obsoletes:      arsse < %{version}

%description
The Arsse bridges the gap between multiple existing newsfeed aggregator
client protocols such as Tiny Tiny RSS, Nextcloud News and Miniflux,
allowing you to use compatible clients for many protocols with a single
server.

%package sqlite
Summary:        SQLite database configuration for The Arsse
Requires:       (php-sqlite or php-pdo_sqlite)
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-postgresql
Conflicts:      arsse-mysql
Provides:       arsse-conf-db
Provides:       arsse-sqlite = %{version}
Obsoletes:      arsse-sqlite < %{version}

%package pgsql
Summary:        PostgreSQL database configuration for The Arsse
Requires:       (php-pgsql or php-pdo_pgsql)
Requires:       postgresql-server >= 10
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-sqlite
Conflicts:      arsse-mysql
Provides:       arsse-conf-db
Provides:       arsse-pgsql = %{version}
Obsoletes:      arsse-pgsql < %{version}

%package mysql
Summary:        MySQL database configuration for The Arsse
Requires:       (php-mysql or php-pdo_mysql)
Requires:       mysql-server >= 8.0
Requires:       %{name} = %{version}-%{release}
Conflicts:      arsse-sqlite
Conflicts:      arsse-postgresql
# OpenSUSE only packages MariaDb, which does not worth with The Arsse
#Provides:      arsse-conf-db
Provides:       arsse-mysql = %{version}
Obsoletes:      arsse-mysql < %{version}

%package nginx-fpm
Summary:        Nginx Web server configuration for The Arsse using PHP-FPM
Requires:       php-fpm >= %{phpver}
Requires:       nginx
Requires:       %{name} = %{version}-%{release}
Provides:       arsse-conf-www
Provides:       arsse-nginx-fpm = %{version}
Obsoletes:      arsse-nginx-fpm < %{version}

%package apache-fpm
Summary:        Apache Web server configuration for The Arsse using PHP-FPM
Requires:       php-fpm >= %{phpver}
Requires:       %{name} = %{version}-%{release}
Requires:       apache >= 2.4
Provides:       arsse-conf-www
Provides:       arsse-apache-fpm = %{version}
Obsoletes:      arsse-apache-fpm < %{version}

%prep
%setup -q -n %{name}
### Perform adjustments to config files here?

%build
# Nothing to do

%install

