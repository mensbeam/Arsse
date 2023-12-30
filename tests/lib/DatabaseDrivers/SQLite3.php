<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;

trait SQLite3 {
    use SQLite3Common;

    protected static $implementation = "SQLite 3";
    protected static $backend = "SQLite 3";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\SQLite3\Result::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\SQLite3\Statement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\SQLite3\Driver::class;
    protected static $stringOutput = false;

    protected static function dbInterface() {
        try {
            $d = new \SQLite3(Arsse::$conf->dbSQLite3File);
        } catch (\Throwable $e) {
            return;
        }
        $d->enableExceptions(true);
        return $d;
    }
}
