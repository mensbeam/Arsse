<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Db\Driver;

trait MySQLCommon {
    public static function dbTableList($db): array {
        $listTables = "SELECT table_name as name from information_schema.tables where table_schema = database() and table_name like 'arsse_%'";
        if ($db instanceof Driver) {
            $tables = $db->query($listTables)->getAll();
        } elseif ($db instanceof \PDO) {
            $tables = $db->query($listTables)->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $tables = $db->query($listTables)->fetch_all(\MYSQLI_ASSOC);
        }
        $tables = sizeof($tables) ? array_column($tables, "name") : [];
        return $tables;
    }

    public static function dbTruncate($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->query("UNLOCK TABLES; ROLLBACK");
        } catch (\Throwable $e) {
        }
        $db->query("SET FOREIGN_KEY_CHECKS=0");
        foreach (self::dbTableList($db) as $table) {
            if ($table === "arsse_meta") {
                $db->query("DELETE FROM $table where `key` <> 'schema_version'");
            } else {
                $db->query("TRUNCATE TABLE $table");
            }
        }
        foreach ($afterStatements as $st) {
            $db->query($st);
        }
        $db->query("SET FOREIGN_KEY_CHECKS=1");
    }

    public static function dbRaze($db, array $afterStatements = []): void {
        // rollback any pending transaction
        try {
            $db->query("UNLOCK TABLES; ROLLBACK");
        } catch (\Throwable $e) {
        }
        $db->query("SET FOREIGN_KEY_CHECKS=0");
        foreach (self::dbTableList($db) as $table) {
            $db->query("DROP TABLE IF EXISTS $table");
        }
        foreach ($afterStatements as $st) {
            $db->query($st);
        }
        $db->query("SET FOREIGN_KEY_CHECKS=1");
    }
}
