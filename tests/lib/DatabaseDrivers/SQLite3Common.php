<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Db\Driver;

trait SQLite3Common {
    public static function dbTableList($db): array {
        $listTables = "SELECT name from sqlite_master where type = 'table' and name like 'arsse^_%' escape '^'";
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
    }

    public static function dbTruncate($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->exec("ROLLBACK");
        } catch (\Throwable $e) {
        }
        foreach (self::dbTableList($db) as $table) {
            if ($table === "arsse_meta") {
                $db->exec("DELETE FROM $table where key <> 'schema_version'");
            } else {
                $db->exec("DELETE FROM $table");
            }
        }
        foreach ($afterStatements as $st) {
            $db->exec($st);
        }
    }

    public static function dbRaze($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->exec("ROLLBACK");
        } catch (\Throwable $e) {
        }
        $db->exec("PRAGMA foreign_keys=0");
        foreach (self::dbTableList($db) as $table) {
            $db->exec("DROP TABLE IF EXISTS $table");
        }
        $db->exec("PRAGMA user_version=0");
        $db->exec("PRAGMA foreign_keys=1");
        foreach ($afterStatements as $st) {
            $db->exec($st);
        }
    }
}