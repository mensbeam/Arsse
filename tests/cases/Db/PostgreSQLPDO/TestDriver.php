<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\PostgreSQLPDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::class)]
class TestDriver extends \JKingWeb\Arsse\TestCase\Db\BaseDriver {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQLPDO;

    protected $create = "CREATE TABLE arsse_test(id bigserial primary key)";
    protected $lock = ["BEGIN", "LOCK TABLE arsse_meta IN EXCLUSIVE MODE NOWAIT"];
    protected $setVersion = "UPDATE arsse_meta set value = '#' where key = 'schema_version'";

    public function tearDown(): void {
        try {
            $this->drv->exec("ROLLBACK");
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }
}
