<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\MySQL;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\MySQL\Driver<extended> */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\TestCase\DatabaseDrivers\MySQL;

    protected $create = "CREATE TABLE arsse_test(id bigint auto_increment primary key)";
    protected $lock = ["SET lock_wait_timeout = 1", "LOCK TABLES arsse_meta WRITE"];
    protected $setVersion = "UPDATE arsse_meta set value = '#' where `key` = 'schema_version'";
    protected static $insertDefaultValues = "INSERT INTO arsse_test(id) values(default)";

    protected function exec($q): bool {
        if (is_array($q)) {
            $q = implode("; ", $q);
        }
        static::$interface->multi_query($q);
        $e = null;
        do {
            if (!$e && static::$interface->sqlstate !== "00000") {
                $e = new \Exception(static::$interface->error);
            }
        } while (static::$interface->more_results() && static::$interface->next_result());
        if ($e) {
            throw $e;
        }
        return true;
    }

    protected function query(string $q) {
        $r = static::$interface->query($q);
        if ($r) {
            $row = $r->fetch_row();
            $r->free();
            if ($row) {
                return $row[0];
            } else {
                return null;
            }
        }
        return null;
    }
}
