<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

/**
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\PDODriver
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    protected $implementation = "PDO PostgreSQL";
    protected $create = "CREATE TABLE arsse_test(id bigserial primary key)";
    protected $lock = "BEGIN; LOCK TABLE arsse_test IN EXCLUSIVE MODE NOWAIT";
    protected $setVersion = "UPDATE arsse_meta set value = '#' where key = 'schema_version'";

    public function tearDown() {
        parent::tearDown();
        unset($this->interface);
    }
}
