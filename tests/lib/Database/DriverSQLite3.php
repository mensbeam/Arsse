<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\SQLite3\Driver;

trait DriverSQLite3 {
    public function setUpDriver() {
        if (!extension_loaded("sqlite3")) {
            $this->markTestSkipped("SQLite extension not loaded");
        }
        Arsse::$conf->dbSQLite3File = ":memory:";
        $this->drv = new Driver(true);
    }

    public function nextID(string $table): int {
        return $this->drv->query("SELECT (case when max(id) then max(id) else 0 end)+1 from $table")->getValue();
    }
}
