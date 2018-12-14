<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestUpdate extends \JKingWeb\Arsse\TestCase\Db\BaseUpdate {
    protected static $implementation = "PDO SQLite 3";
    protected static $minimal1 = "create table arsse_meta(key text primary key not null, value text); pragma user_version=1";
    protected static $minimal2 = "pragma user_version=2";
}
