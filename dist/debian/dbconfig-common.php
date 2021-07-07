<?php

# This script transforms Debian's dbconfig-common PHP-format include files
# into a form usable by The Arsse. This is necessary because The Arsse
# supports defining configuration parameters for all supported database types
# at once, using separate keys for the different types

$dbconfpath = "/var/lib/arsse/dbconfig.inc"; // path defined in postinst script

if (file_exists($dbconfpath)) {
    require_once "/var/lib/arsse/dbconfig.inc";
    $dbtype = $dbtype ?? "";
    // the returned configuration depends on the $dbtype
    if ($dbtype === "sqlite3") {
        $conf = ['dbDriver' => "sqlite3"];
        if (strlen((string) $basepath) && strlen((string) $dbname)) {
            $conf['dbSQLite3File'] = "$basepath/$dbname";
        }
    } elseif ($dbtype === "pgsql") {
        $conf = [
            'dbDriver' => "postgresql",
            'dbPostgreSQLHost' => $dbserver ?? "",
            'dbPostgreSQLUser' => $dbuser ?? "arsse",
            'dbPostgreSQLPass' => $dbpass ?? "",
            'dbPostgreSQLPort' => (int) $dbport ?: 5432,
            'dbPostgreSQLDb'   => $dbname ?? "arsse",
        ];
    } elseif ($dbtype === "mysql") {
        $conf = [
            'dbDriver' => "mysql",
            'dbMySQLHost' => $dbserver ?? "",
            'dbMySQLUser' => $dbuser ?? "arsse",
            'dbMySQLPass' => $dbpass ?? "",
            'dbMySQLPort' => (int) $dbport ?: 3306,
            'dbMySQLDb'   => $dbname ?? "arsse",
        ];
    } else {
        throw new \Exception("Debian dbconfig-common configuration file $dbconfpath is invalid");
    }
    return $conf;
} else {
    // if no configuration file exists simply return an empty array
    return [];
}
