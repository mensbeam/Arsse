# Preface

This section details a few administrative tasks which may need to be performed after installing The Arsse. As no Web-based administrative interface is included, these tasks are generally performed via command line interface.

Though this section describes some commands briefly, complete documentation of The Arsse's command line interface is not included in this manual. Documentation for CLI commands can instead be viewed with the CLI itself by executing `php arsse.php --help`.

# A Note on Command Invocation

Particularly if using an SQLite database, it's important that administrative commands be executed as the same user who owns The Arsse's files. To that end the examples in this section all use the verbose formulation `sudo -u www-data php arsse.php` (with `www-data` being the user under which Web servers run in Debian), but it is possible to simplify invocation to `sudo arsse` if an executable file named `arsse` is created somewhere in the sudo path with the following content:

```php
#! /usr/bin/env php
<?php
if (posix_geteuid() == 0) {
    $info = posix_getpwnam("www-data");
    posix_setegid($info['gid']);
    posix_seteuid($info['uid']);
}
include "/usr/share/arsse/arsse.php";
```
