<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQL;

use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
#[Group('coverageOptional')]
#[CoversClass(Database::class)]
class TestDatabase extends \JKingWeb\Arsse\TestCase\Database\AbstractTest {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\MySQL;

    protected function nextID(string $table): int {
        return (int) (static::$drv->query("SELECT (case when max(id) then max(id) else 0 end)+1 from $table")->getValue() ?? 1);
    }
}
