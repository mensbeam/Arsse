<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class TestDatabaseUserSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\Database\Setup;
    use Test\Database\DriverSQLite3;
    use Test\Database\SeriesUser;
}