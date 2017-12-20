<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

/** 
 * @covers \JKingWeb\Arsse\Database<extended> 
 * @group optional */
class TestDatabaseMiscellanySQLite3PDO extends Test\AbstractTest {
    use Test\Database\Setup;
    use Test\Database\DriverSQLite3PDO;
    use Test\Database\SeriesMiscellany;
}
