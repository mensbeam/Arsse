#! /usr/bin/bash

if [ `id -u` -eq 0 ]; then
    setpriv --clear-groups --inh-caps -all --egid=arsse --euid=arsse php /usr/share/webapps/arsse/arsse.php $@
elif [ `id -un` == "arsse" ]; then
    php /usr/share/webapps/arsse/arsse.php $@
else
    echo "Not authorized." >&2
    exit 1
fi
