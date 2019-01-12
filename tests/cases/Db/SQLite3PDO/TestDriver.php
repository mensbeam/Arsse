<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\PDODriver
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\TestCase\DatabaseDrivers\SQLite3PDO;

    protected $create = "CREATE TABLE arsse_test(id integer primary key)";
    protected $lock = "BEGIN EXCLUSIVE TRANSACTION";
    protected $setVersion = "PRAGMA user_version=#";
    protected static $file;

    public static function setUpBeforeClass() {
        // create a temporary database file rather than using a memory database
        // some tests require one connection to block another, so a memory database is not suitable
        static::$file = tempnam(sys_get_temp_dir(), 'ook');
        static::$conf['dbSQLite3File'] = static::$file;
        parent::setUpBeforeclass();
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        @unlink(self::$file);
        self::$file = null;
    }
}
