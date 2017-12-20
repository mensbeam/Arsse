<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;

trait DriverSQLite3PDO {
    public function setUpDriver() {
        if (!PDODriver::requirementsMet()) {
            $this->markTestSkipped("PDO-SQLite extension not loaded");
        }
        Arsse::$conf->dbSQLite3File = ":memory:";
        $this->drv = new PDODriver();
    }

    public function nextID(string $table): int {
        return (int) $this->drv->query("SELECT (case when max(id) then max(id) else 0 end)+1 from $table")->getValue();
    }
}
