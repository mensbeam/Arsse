<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;

trait SQLite3PDO {
    protected static $implementation = "PDO SQLite 3";
    protected static $backend = "SQLite 3";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\PDOResult::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\SQLite3\PDOStatement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\SQLite3\PDODriver::class;
    protected static $stringOutput = true;
    
    public static function dbInterface() {
        try {
            $d = new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $d->exec("PRAGMA busy_timeout=0");
            return $d;
        } catch (\Throwable $e) {
            return;
        }
    }
    
    public static function dbTableList($db): array {
        return SQLite3::dbTableList($db);
    }

    public static function dbTruncate($db, array $afterStatements = []): void {
        SQLite3::dbTruncate($db, $afterStatements);
    }

    public static function dbRaze($db, array $afterStatements = []): void {
        SQLite3::dbRaze($db, $afterStatements);
    }
}
