<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PosgreSQL;

/** 
 * @covers \JKingWeb\Arsse\Database<extended>
 * @covers \JKingWeb\Arsse\Misc\Query<extended>
 */
class TestDatabase extends \JKingWeb\Arsse\TestCase\Database\Base {
    protected static $implementation = "PDO PostgreSQL";

    protected function nextID(string $table): int {
        return static::$drv->query("SELECT select cast(last_value as bigint) + 1 from pg_sequences where sequencename = '{$table}_id_seq'")->getValue();
    }
}
