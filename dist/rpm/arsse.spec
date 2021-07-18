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
# User and Group
Requires:       user(arsse) group(arsse)

%systemd_requires

Recommends:     arsse-sqlite
Suggests:       php-curl
Obsoletes:      arsse < %{version}

BuildRequires:  systemd-rpm-macros
BuildRequires:  sysuser-tools

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

%description sqlite
Configures The Arsse to use an SQLite database. This is the default
option and is suitable for most installations

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

%description pgsql
Configures The Arsse to use a PostgreSQL database.

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

%description mysql
Configures The Arsse to use a MySQL database. Using this package is not
recommended, but it is provided for those who wish to use an existing MySQL
installation.

Note that MariaDb is not compatible. See https://jira.mariadb.org/browse/MDEV-18511
for details.

%package config-nginx-fpm
Summary:        Nginx Web server configuration for The Arsse using PHP-FPM
Group:          Productivity/Networking/Web/Utilities
Requires:       php-fpm >= %{minver}
Requires:       nginx
Requires:       %{name} = %{version}-%{release}
Provides:       arsse-config-nginx-fpm = %{version}
Obsoletes:      arsse-config-nginx-fpm < %{version}
Supplements:    packageand(apache2:arsse)

%description config-nginx-fpm
Nginx Web server configuration for The Arsse using PHP-FPM. Using Ngix is
generally preferred as it receives more testing.

%package config-apache-fpm
Summary:        Apache Web server configuration for The Arsse using PHP-FPM
Group:          Productivity/Networking/Web/Utilities
Requires:       php-fpm >= %{minver}
Requires:       %{name} = %{version}-%{release}
Requires:       apache >= 2.4
Provides:       arsse-config-apache-fpm = %{version}
Obsoletes:      arsse-config-apache-fpm < %{version}
Supplements:    packageand(apache2:arsse)

%description config-apache-fpm
Apache Web server configuration for The Arsse using PHP-FPM. Using Ngix is
generally preferred as it receives more testing.

%package -n system-user-arsse
Summary:        System user and group arsse
Group:          System/Fhs
%{sysusers_requires}

%description -n system-user-arsse
This package provides the system account and group 'arsse'.

%prep
%setup -q -n %{name}
# Patch the executable so it does not use env as the interpreter; RPMLint complains about this
sed -i -se 's/#! \?\/usr\/bin\/env php/#! \/usr\/bin\/php/' dist/arsse
# Remove stray executable
rm -f vendor/nicolus/picofeed/picofeed

%build
%sysusers_generate_pre dist/sysuser.conf arsse system-user-arsse.conf

%install
mkdir -p "%{buildroot}%{_datadir}/php/arsse" "%{buildroot}%{_mandir}" "%{buildroot}%{_unitdir}" "%{buildroot}%{_sysusersdir}" "%{buildroot}%{_bindir}"
cp -r lib locale sql vendor www CHANGELOG UPGRADING README.md arsse.php "%{buildroot}%{_datadir}/php/arsse"
cp -r dist/man/* "%{buildroot}%{_mandir}"
cp dist/systemd/arsse-fetch.service "%{buildroot}%{_unitdir}/arsse.service"
install -m 755 dist/arsse "%{buildroot}%{_bindir}/arsse"
install -m 644 dist/sysuser.conf %{buildroot}%{_sysusersdir}/system-user-arsse.conf

%files
%dir %{_datadir}/php
%license LICENSE AUTHORS
%doc manual/*
%{_datadir}/php/arsse
%{_mandir}/man*/arsse.*
%{_bindir}/arsse
%{_unitdir}/arsse.service

%files -n system-user-arsse
%{_sysusersdir}/system-user-arsse.conf

%pre
%service_add_pre arsse.service demo1.service

%post
%service_add_post arsse.service demo1.service

%preun
%service_del_preun arsse.service

%postun
%service_del_postun arsse.service
%service_del_postun_without_restart arsse.service

%pre -n system-user-arsse -f arsse.pre