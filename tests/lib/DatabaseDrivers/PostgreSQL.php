<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;

trait PostgreSQL {
    use PostgreSQLCommon;

    protected static $implementation = "PostgreSQL";
    protected static $backend = "PostgreSQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\PostgreSQL\Result::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\PostgreSQL\Statement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\PostgreSQL\Driver::class;
    protected static $stringOutput = true;

    public static function dbInterface() {
        $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(false, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
        if (function_exists("pg_connect") && $d = @pg_connect($connString, \PGSQL_CONNECT_FORCE_NEW)) {
            foreach (\JKingWeb\Arsse\Db\PostgreSQL\Driver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
                pg_query($d, $q);
            }
            return $d;
        } else {
            return;
        }
    }
}
