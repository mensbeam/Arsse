<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Driver;

class DatabaseInformation {
    public $name;
    public $backend;
    public $pdo;
    public $resultClass;
    public $statementClass;
    public $driverClass;
    public $stringOutput;
    public $interfaceConstructor;
    public $truncateFunction;
    public $razeFunction;

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
        $sqlite3TableList = function($db): array {
            $listTables = "SELECT name from sqlite_master where type = 'table' and name like 'arsse_%'";
            if ($db instanceof Driver) {
                $tables = $db->query($listTables)->getAll();
                $tables = sizeof($tables) ? array_column($tables, "name") : [];
            } elseif ($db instanceof \PDO) {
                retry:
                try {
                    $tables = $db->query($listTables)->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\PDOException $e) {
                    goto retry;
                }
                $tables = sizeof($tables) ? array_column($tables, "name") : [];
            } else {
                $tables = [];
                $result = $db->query($listTables);
                while ($r = $result->fetchArray(\SQLITE3_ASSOC)) {
                    $tables[] = $r['name'];
                }
                $result->finalize();
            }
            return $tables;
        };
        $sqlite3TruncateFunction = function($db, array $afterStatements = []) use ($sqlite3TableList) {
            // rollback any pending transaction
            try {
                $db->exec("ROLLBACK");
            } catch(\Throwable $e) {
            }
            foreach ($sqlite3TableList($db) as $table) {
                if ($table == "arsse_meta") {
                    $db->exec("DELETE FROM $table where key <> 'schema_version'");
                } else {
                    $db->exec("DELETE FROM $table");
                }
            }
            foreach ($afterStatements as $st) {
                $db->exec($st);
            }
        };
        $sqlite3RazeFunction = function($db, array $afterStatements = []) use ($sqlite3TableList) {
            // rollback any pending transaction
            try {
                $db->exec("ROLLBACK");
            } catch(\Throwable $e) {
            }
            $db->exec("PRAGMA foreign_keys=0");
            foreach ($sqlite3TableList($db) as $table) {
                $db->exec("DROP TABLE IF EXISTS $table");
            }
            $db->exec("PRAGMA user_version=0");
            $db->exec("PRAGMA foreign_keys=1");
            foreach ($afterStatements as $st) {
                $db->exec($st);
            }
        };
        $pgObjectList = function($db): array {
            $listObjects = "SELECT table_name as name, 'TABLE' as type from information_schema.tables where table_schema = current_schema() and table_name like 'arsse_%' union SELECT collation_name as name, 'COLLATION' as type from information_schema.collations where collation_schema = current_schema()";
            if ($db instanceof Driver) {
                return $db->query($listObjects)->getAll();
            } elseif ($db instanceof \PDO) {
                return $db->query($listObjects)->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                throw \Exception("Native PostgreSQL interface not implemented");
            }
        };
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
                'truncateFunction' => $sqlite3TruncateFunction,
                'razeFunction' => $sqlite3RazeFunction,
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
                'truncateFunction' => $sqlite3TruncateFunction,
                'razeFunction' => $sqlite3RazeFunction,
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
                'truncateFunction' => function($db, array $afterStatements = []) use ($pgObjectList) {
                    // rollback any pending transaction
                    try {
                        $db->exec("ROLLBACK");
                    } catch(\Throwable $e) {
                    }
                    foreach ($pgObjectList($db) as $obj) {
                        if ($obj['type'] != "TABLE") {
                            continue;
                        } elseif ($obj['name'] == "arsse_meta") {
                            $db->exec("DELETE FROM {$obj['name']} where key <> 'schema_version'");
                        } else {
                            $db->exec("TRUNCATE TABLE {$obj['name']} restart identity cascade");
                        }
                    }
                    foreach ($afterStatements as $st) {
                        $db->exec($st);
                    }
                },
                'razeFunction' => function($db, array $afterStatements = []) use ($pgObjectList) {
                    // rollback any pending transaction
                    try {
                        $db->exec("ROLLBACK");
                    } catch(\Throwable $e) {
                    }
                    foreach ($pgObjectList($db) as $obj) {
                        $db->exec("DROP {$obj['type']} IF EXISTS {$obj['name']} cascade");
                    }
                    foreach ($afterStatements as $st) {
                        $db->exec($st);
                    }
                },
            ],
        ];
    }
}
