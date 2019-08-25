<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\MySQL\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\MySQL\ExceptionBuilder
 * @covers \JKingWeb\Arsse\Db\PDODriver
 * @covers \JKingWeb\Arsse\Db\PDOError
 * @covers \JKingWeb\Arsse\Db\SQLState */
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\MySQLPDO;

    protected $create = "CREATE TABLE arsse_test(id bigint auto_increment primary key)";
    protected $lock = ["SET lock_wait_timeout = 1", "LOCK TABLES arsse_meta WRITE"];
    protected $setVersion = "UPDATE arsse_meta set value = '#' where `key` = 'schema_version'";
    protected static $insertDefaultValues = "INSERT INTO arsse_test(id) values(default)";
}
