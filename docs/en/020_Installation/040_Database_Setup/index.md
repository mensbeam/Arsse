The Arsse supports the following database backends:

- SQLite 3.8.3 and later
- PostgreSQL 10 and later
- MySQL 8.07 and later

All of the above are supported both via their PDO driver extensions as well as their native PHP extensions. One or the other is selected based on availability in your PHP installation.

Functionally there is no reason to prefer either SQLite or PostgreSQL over the other. SQLite is significantly simpler to set up in most cases, requiring only read and write access to a containing directory in order to function; PostgreSQL may perform better than SQLite when serving hundreds of users or more, though this has not been tested.

MySQL, on the other hand, is **not recommended** due to its relatively constrained index prefix limits which may cause some newsfeeds which would otherwise work to be rejected. If using MySQL, special care should also be taken when performing schema upgrades, as errors during the process can leave the database in a half-upgraded state which The Arsse cannot itself recover from.

Note that MariaDB is not compatible with The Arsse: its support for common table expressions is, as of this writing, not sufficient for our needs.
