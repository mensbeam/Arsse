<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Db\SQLite3\Driver;

trait DriverSQLite3 {
    function setUpDriver() {
        Data::$conf->dbSQLite3File = ":memory:";
        $this->drv = new Driver(true);
    }
}