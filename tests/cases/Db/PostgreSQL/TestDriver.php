<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\Driver<extended>
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\Dispatch<extended>
 * @covers \JKingWeb\Arsse\Db\SQLState */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQL;

    protected $create = "CREATE TABLE arsse_test(id bigserial primary key)";
    protected $lock = ["BEGIN", "LOCK TABLE arsse_meta IN EXCLUSIVE MODE NOWAIT"];
    protected $setVersion = "UPDATE arsse_meta set value = '#' where key = 'schema_version'";

    public function tearDown() {
        try {
            $this->drv->exec("ROLLBACK");
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public static function tearDownAfterClass() {
        if (static::$interface) {
            static::dbRaze(static::$interface);
            @pg_close(static::$interface);
            static::$interface = null;
        }
        parent::tearDownAfterClass();
    }

    protected function exec($q): bool {
        $q = (!is_array($q)) ? [$q] : $q;
        foreach ($q as $query) {
            set_error_handler(function($code, $msg) {
                throw new \Exception($msg);
            });
            try {
                pg_query(static::$interface, $query);
            } finally {
                restore_error_handler();
            }
        }
        return true;
    }

    protected function query(string $q) {
        if ($r = pg_query_params(static::$interface, $q, [])) {
            return pg_fetch_result($r, 0, 0);
        } else {
            return;
        }
    }
}
