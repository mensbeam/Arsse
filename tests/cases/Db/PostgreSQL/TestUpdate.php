<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\PostgreSQL\Driver::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestUpdate extends \JKingWeb\Arsse\TestCase\Db\BaseUpdate {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQL;

    protected static $minimal1 = "CREATE TABLE arsse_meta(key text primary key, value text); INSERT INTO arsse_meta(key,value) values('schema_version','1');";
    protected static $minimal2 = "UPDATE arsse_meta set value = '2' where key = 'schema_version';";
}
