<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class TestDatabaseSubscriptionSQLite3 extends Test\AbstractTest {
    use Test\Database\Setup;
    use Test\Database\DriverSQLite3;
    use Test\Database\SeriesSubscription;
}