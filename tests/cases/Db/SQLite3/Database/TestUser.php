<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3\Database;

/** @covers \JKingWeb\Arsse\Database<extended> */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\Test\Database\Setup;
    use \JKingWeb\Arsse\Test\Database\DriverSQLite3;
    use \JKingWeb\Arsse\Test\Database\SeriesUser;
}
