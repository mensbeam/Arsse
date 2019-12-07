<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\Driver<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\SQLite3;

    protected $create = "CREATE TABLE arsse_test(id integer primary key)";
    protected $lock = "BEGIN EXCLUSIVE TRANSACTION";
    protected $setVersion = "PRAGMA user_version=#";
    protected static $file;

    public static function setUpBeforeClass(): void {
        // create a temporary database file rather than using a memory database
        // some tests require one connection to block another, so a memory database is not suitable
        static::$file = tempnam(sys_get_temp_dir(), 'ook');
        static::$conf['dbSQLite3File'] = static::$file;
        parent::setUpBeforeclass();
    }

    public static function tearDownAfterClass(): void {
        static::$interface->close();
        static::$interface = null;
        parent::tearDownAfterClass();
        @unlink(static::$file);
        static::$file = null;
    }

    protected function exec($q): bool {
        // SQLite's implementation coincidentally matches PDO's, but we reproduce it here for correctness' sake
        $q = (!is_array($q)) ? [$q] : $q;
        foreach ($q as $query) {
            static::$interface->exec((string) $query);
        }
        return true;
    }

    protected function query(string $q) {
        return static::$interface->querySingle($q);
    }
}
