Name:           arsse
Version:        0.10.0
Release:        0
Summary:        Multi-protocol RSS/Atom newsfeed synchronization server
License:        MIT
Group:          Productivity/Networking/Web/Utilities
URL:            https://thearsse.com/
Source0:        %{name}-%{version}.tar.gz
BuildArch:      noarch

%define minphpver  7.1
%define arssepath  %{_datadir}/php/arsse
%define socketpath %{_rundir}/php-fpm/arsse.sock

Requires:       php >= %{minphpver}
Requires:       php-intl php-dom php-posix php-pcntl
Requires:       php-simplexml php-iconv
# This is usually compiled in
Requires:       php-filter
# The below extensions are part of the PHP core in recent versions
Requires:       php-hash php-json
# A database option is required
Requires:       (php-sqlite or php-pgsql)
# User and Group
Requires:       user(arsse) group(arsse)

%systemd_requires

Recommends:     php-sqlite
Suggests:       php-curl
Suggests:       (php-pgsql if postgresql-server)
Obsoletes:      arsse < %{version}

BuildRequires:  systemd-rpm-macros
BuildRequires:  apache-rpm-macros
BuildRequires:  sysuser-tools

%description
The Arsse bridges the gap between multiple existing newsfeed aggregator
client protocols such as Tiny Tiny RSS, Nextcloud News and Miniflux,
allowing you to use compatible clients for many protocols with a single
server.

%package config-fpm
Summary:        PHP-FPM process pool configuration for The Arsse
Group:          Productivity/Networking/Web/Utilities
Requires:       php-fpm >= %{minphpver}
Requires:       %{name} = %{version}-%{release}
Provides:       arsse-config-fpm = %{version}
Obsoletes:      arsse-config-fpm < %{version}
Supplements:    packageand(php-fpm:arsse)

%description config-fpm
PHP-FPM process pool configuration for The Arsse

%package config-nginx-fpm
Summary:        Nginx Web server configuration for The Arsse using PHP-FPM
Group:          Productivity/Networking/Web/Utilities
Requires:       arsse-fpm
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
Requires:       arsse-fpm
Requires:       %{name} = %{version}-%{release}
Requires:       apache2 >= 2.4
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
sed -i -se 's|/usr/bin/env php|{_bindir}/php|' dist/arsse
# Remove stray executable
rm -f vendor/nicolus/picofeed/picofeed
# Patch the systemd unit file to remove the binding to the PHP-FPM service
sed -i -se 's|^PartOf=.*||' dist/systemd/arsse-fetch.service
# Patch PHP-FPM pool and Web server configuration with correct socket path
sed -i -se 's|/var/run/php/arsse\.sock|%{socketpath}|' dist/php-fpm.conf dist/nginx/* dist/apache/*
# Patch various files to adjust installation path
sed -i -se 's|/usr/share/arsse/|%{arssepath}/|' dist/arsse dist/nginx/* dist/apache/*
sed -i -se 's|/usr/share/arsse|%{arssepath}|' dist/systemd/arsse-fetch.service
# Patch configuration files to adjust other paths (they're probably already correct)
sed -i -se 's|/etc/arsse/|%{_sysconfdir}/arsse/|' dist/nginx/* dist/apache/*
sed -i -se 's|/usr/bin/|%{_bindir}/|' dist/systemd/arsse-fetch.service
# Patch Web server configuration to use unique hostname; "news" is recommended, but might conflict with other example configuration
sed -i -se 's|news.example.com|arsse.example.com|' dist/nginx/* dist/apache/*

%build
%sysusers_generate_pre dist/sysuser.conf arsse system-user-arsse.conf

%install
mkdir -p "%{buildroot}%{_mandir}" "%{buildroot}%{_unitdir}" "%{buildroot}%{_sysusersdir}" "%{buildroot}%{_bindir}" "%{buildroot}%{_sysconfdir}/arsse"
mkdir -p "%{buildroot}%{arssepath}" "%{buildroot}%{_sysconfdir}/arsse/nginx" "%{buildroot}%{_sysconfdir}/arsse/apache"
cp -r lib locale sql vendor www CHANGELOG UPGRADING README.md arsse.php "%{buildroot}%{arssepath}"
cp -r dist/man/* "%{buildroot}%{_mandir}"
cp dist/systemd/arsse-fetch.service "%{buildroot}%{_unitdir}/arsse.service"
install -D dist/php-fpm.conf "%{buildroot}%{_sysconfdir}/php7/fpm/php-fpm.d/arsse.conf"
install -D dist/php-fpm.conf "%{buildroot}%{_sysconfdir}/php8/fpm/php-fpm.d/arsse.conf"
install dist/nginx/arsse* "%{buildroot}%{_sysconfdir}/arsse/nginx"
install dist/apache/arsse* "%{buildroot}%{_sysconfdir}/arsse/apache"
install -m 755 dist/arsse "%{buildroot}%{_bindir}/arsse"
install -m 644 dist/sysuser.conf %{buildroot}%{_sysusersdir}/system-user-arsse.conf

%files
%dir %{_datadir}/php
%dir %{_sysconfdir}/arsse
%{arssepath}
%{_mandir}/man*/arsse.*
%{_unitdir}/arsse.service
%attr(755, root, root) %{_bindir}/arsse
%license LICENSE AUTHORS
%doc manual/*

%files config-fpm
%dir %{_sysconfdir}/php7
%dir %{_sysconfdir}/php8
%dir %{_sysconfdir}/php7/fpm
%dir %{_sysconfdir}/php8/fpm
%dir %{_sysconfdir}/php7/fpm/php-fpm.d
%dir %{_sysconfdir}/php8/fpm/php-fpm.d
%{_sysconfdir}/php7/fpm/php-fpm.d/arsse.conf
%{_sysconfdir}/php8/fpm/php-fpm.d/arsse.conf

%files config-nginx-fpm
%dir %{_sysconfdir}/arsse
%dir %{_sysconfdir}/arsse/nginx
%{_sysconfdir}/arsse/nginx/arsse*

%files config-apache-fpm
%dir %{_sysconfdir}/arsse
%dir %{_sysconfdir}/arsse/apache
%{_sysconfdir}/arsse/apache/arsse*

%files -n system-user-arsse
%{_sysusersdir}/system-user-arsse.conf

%pre
%service_add_pre arsse.service arsse.service

%post
%service_add_post arsse.service arsse.service

%preun
%service_del_preun arsse.service

%postun
%service_del_postun arsse.service
%service_del_postun_without_restart arsse.service

%pre -n system-user-arsse -f arsse.pre
