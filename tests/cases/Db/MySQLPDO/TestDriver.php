<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\MySQL\PDODriver::class)]
#[CoversClass(\JKingWeb\Arsse\Db\MySQL\ExceptionBuilder::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDODriver::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDOError::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\MySQLPDO;

    protected $create = "CREATE TABLE arsse_test(id bigint auto_increment primary key)";
    protected $lock = ["SET lock_wait_timeout = 1", "LOCK TABLES arsse_meta WRITE"];
    protected $setVersion = "UPDATE arsse_meta set value = '#' where `key` = 'schema_version'";
    protected static $insertDefaultValues = "INSERT INTO arsse_test(id) values(default)";
}
