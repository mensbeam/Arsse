<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Arsse;

class DatabaseInformation {
    public $name;
    public $backend;
    public $pdo;
    public $resultClass;
    public $statementClass;
    public $driverClass;
    public $stringOutput;
    public $interfaceConstructor;

    protected static $data;

    public function __construct(string $name) {
        if (!isset(self::$data)) {
            self::$data = self::getData();
        }
        if (!isset(self::$data[$name])) {
            throw new \Exception("Invalid database driver name");
        }
        $this->name = $name;
        foreach (self::$data[$name] as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function list(): array {
        if (!isset(self::$data)) {
            self::$data = self::getData();
        }
        return array_keys(self::$data);
    }

    public static function listPDO(): array {
        if (!isset(self::$data)) {
            self::$data = self::getData();
        }
        return array_values(array_filter(array_keys(self::$data), function($k) {
            return self::$data[$k]['pdo'];
        }));
    }

    protected static function getData() {
        return [
            'SQLite 3' => [
                'pdo' => false,
                'backend' => "SQLite 3",
                'statementClass' => \JKingWeb\Arsse\Db\SQLite3\Statement::class,
                'resultClass' => \JKingWeb\Arsse\Db\SQLite3\Result::class,
                'driverClass' => \JKingWeb\Arsse\Db\SQLite3\Driver::class,
                'stringOutput' => false,
                'interfaceConstructor' => function() {
                    try {
                        $d = new \SQLite3(Arsse::$conf->dbSQLite3File);
                    } catch (\Throwable $e) {
                        return;
                    }
                    $d->enableExceptions(true);
                    return $d;
                },

            ],
            'PDO SQLite 3' => [
                'pdo' => true,
                'backend' => "SQLite 3",
                'statementClass' => \JKingWeb\Arsse\Db\PDOStatement::class,
                'resultClass' => \JKingWeb\Arsse\Db\PDOResult::class,
                'driverClass' => \JKingWeb\Arsse\Db\SQLite3\PDODriver::class,
                'stringOutput' => true,
                'interfaceConstructor' => function() {
                    try {
                        $d = new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                        $d->exec("PRAGMA busy_timeout=0");
                        return $d;
                    } catch (\Throwable $e) {
                        return;
                    }
                },
            ],
            'PDO PostgreSQL' => [
                'pdo' => true,
                'backend' => "PostgreSQL",
                'statementClass' => \JKingWeb\Arsse\Db\PDOStatement::class,
                'resultClass' => \JKingWeb\Arsse\Db\PDOResult::class,
                'driverClass' => \JKingWeb\Arsse\Db\PostgreSQL\PDODriver::class,
                'stringOutput' => true,
                'interfaceConstructor' => function() {
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
                },
            ],
        ];
    }
}
