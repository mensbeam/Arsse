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
    protected $implementation = "PDO SQLite 3";
    protected $create = "CREATE TABLE arsse_test(id integer primary key)";
    protected $lock = "BEGIN EXCLUSIVE TRANSACTION";
    protected $setVersion = "PRAGMA user_version=#";
    protected static $file;

    public static function setUpBeforeClass() {
        self::$file = tempnam(sys_get_temp_dir(), 'ook');
    }

    public static function tearDownAfterClass() {
        @unlink(self::$file);
        self::$file = null;
    }
    
    public function setUp() {
        $this->conf['dbSQLite3File'] = self::$file;
        parent::setUp();
        $this->exec("PRAGMA user_version=0");
    }

    public function tearDown() {
        parent::tearDown();
        $this->exec("PRAGMA user_version=0");
        unset($this->interface);
    }
}
