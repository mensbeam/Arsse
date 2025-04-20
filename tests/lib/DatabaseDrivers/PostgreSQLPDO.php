<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;

trait PostgreSQLPDO {
    use PostgreSQLCommon;

    protected static $implementation = "PDO PostgreSQL";
    protected static $backend = "PostgreSQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\PostgreSQL\PDOResult::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\PostgreSQL\PDOStatement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\PostgreSQL\PDODriver::class;
    protected static $stringOutput = false;

    public static function dbInterface() {
        $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(true, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
        try {
            $d = new \PDO("pgsql:".$connString, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\Throwable $e) {
            return;
        }
        foreach (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
            $d->exec($q);
        }
        return $d;
    }
}
