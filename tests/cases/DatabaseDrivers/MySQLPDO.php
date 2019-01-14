<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Driver;

trait MySQLPDO {
    protected static $implementation = "PDO MySQL";
    protected static $backend = "MySQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\PDOResult::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\MySQL\PDOStatement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\MySQL\PDODriver::class;
    protected static $stringOutput = true;
    
    public static function dbInterface() {
        try {
            $dsn = [];
            $params = [
                'charset' => "utf8mb4",
                'host' => Arsse::$conf->dbMySQLHost,
                'port' => Arsse::$conf->dbMySQLPort,
                'dbname' => Arsse::$conf->dbMySQLDb,
            ];
            foreach ($params as $k => $v) {
                $dsn[] = "$k=$v";
            }
            $dsn = "mysql:".implode(";", $dsn);
            $d = new \PDO($dsn, Arsse::$conf->dbMySQLUser, Arsse::$conf->dbMySQLPass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
            foreach (\JKingWeb\Arsse\Db\MySQL\PDODriver::makeSetupQueries() as $q) {
                $d->exec($q);
            }
            return $d;
        } catch (\Throwable $e) {
            return;
        }
    }
    
    public static function dbTableList($db): array {
        return MySQL::dbTableList($db);
    }

    public static function dbTruncate($db, array $afterStatements = []) {
        MySQL::dbTruncate($db, $afterStatements);
    }

    public static function dbRaze($db, array $afterStatements = []) {
        MySQL::dbRaze($db, $afterStatements);
    }
}
