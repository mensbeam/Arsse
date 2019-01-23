<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\MySQL;

/**
 * @group slow
 * @group coverageOptional
 * @covers \JKingWeb\Arsse\Database<extended>
 * @covers \JKingWeb\Arsse\Misc\Query<extended>
 */
class TestDatabase extends \JKingWeb\Arsse\TestCase\Database\Base {
    use \JKingWeb\Arsse\TestCase\DatabaseDrivers\MySQL;

    protected function nextID(string $table): int {
        return (int) (static::$drv->query("SELECT auto_increment from information_schema.tables where table_name = '$table'")->getValue() ?? 1);
    }
}