<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\DatabaseDrivers;

use JKingWeb\Arsse\Arsse;

trait MySQL {
    use MySQLCommon;

    protected static $implementation = "MySQL";
    protected static $backend = "MySQL";
    protected static $dbResultClass = \JKingWeb\Arsse\Db\MySQL\Result::class;
    protected static $dbStatementClass = \JKingWeb\Arsse\Db\MySQL\Statement::class;
    protected static $dbDriverClass = \JKingWeb\Arsse\Db\MySQL\Driver::class;
    protected static $stringOutput = true;

    public static function dbInterface() {
        if (!class_exists("mysqli")) {
            return null;
        }
        $drv = new \mysqli_driver;
        $drv->report_mode = \MYSQLI_REPORT_OFF;
        $d = mysqli_init();
        $d->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, false);
        $d->options(\MYSQLI_SET_CHARSET_NAME, "utf8mb4");
        @$d->real_connect(Arsse::$conf->dbMySQLHost, Arsse::$conf->dbMySQLUser, Arsse::$conf->dbMySQLPass, Arsse::$conf->dbMySQLDb, Arsse::$conf->dbMySQLPort);
        if ($d->connect_errno) {
            return null;
        }
        $d->set_charset("utf8mb4");
        foreach (\JKingWeb\Arsse\Db\MySQL\PDODriver::makeSetupQueries() as $q) {
            $d->query($q);
        }
        return $d;
    }
}
