<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

/** 
 * @covers \JKingWeb\Arsse\Database<extended> 
 * @group optional */
class TestDatabaseSessionSQLite3PDO extends Test\AbstractTest {
    use Test\Database\Setup;
    use Test\Database\DriverSQLite3PDO;
    use Test\Database\SeriesSession;
}
