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
    protected $implementation = "SQLite 3";
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
        $this->interface->close();
        unset($this->interface);
    }

    protected function exec(string $q): bool {
        $this->interface->exec($q);
        return true;
    }

    protected function query(string $q) {
        return $this->interface->querySingle($q);
    }

    public function provideDrivers() {
        self::clearData();
        self::setConf([
            'dbTimeoutExec' => 0.5,
            'dbSQLite3Timeout' => 0,
            'dbSQLite3File' => tempnam(sys_get_temp_dir(), 'ook'),
        ]);
        $i = $this->provideDbInterfaces();
        $d = $this->provideDbDrivers();
        $pdoExec = function (string $q) {
            $this->interface->exec($q);
            return true;
        };
        $pdoQuery = function (string $q) {
            return $this->interface->query($q)->fetchColumn();
        };
        return [
            'SQLite 3' => [
                $i['SQLite 3']['interface'], 
                $d['SQLite 3'], 
                "CREATE TABLE arsse_test(id integer primary key)", 
                "BEGIN EXCLUSIVE TRANSACTION",
                "PRAGMA user_version=#",
                function (string $q) {
                    $this->interface->exec($q);
                    return true;
                },
                function (string $q) {
                    return $this->interface->querySingle($q);
                },
            ],
            'PDO SQLite 3' => [
                $i['PDO SQLite 3']['interface'], 
                $d['PDO SQLite 3'], 
                "CREATE TABLE arsse_test(id integer primary key)", 
                "BEGIN EXCLUSIVE TRANSACTION",
                "PRAGMA user_version=#",
                $pdoExec,
                $pdoQuery,
            ],
            'PDO PostgreSQL' => [
                $i['PDO PostgreSQL']['interface'], 
                $d['PDO PostgreSQL'], 
                "CREATE TABLE arsse_test(id bigserial primary key)", 
                "BEGIN; LOCK TABLE arsse_test IN EXCLUSIVE MODE NOWAIT",
                "UPDATE arsse_meta set value = '#' where key = 'schema_version'",
                $pdoExec,
                $pdoQuery,
            ],
        ];
    }
}
