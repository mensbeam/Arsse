<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3\Database;

/**
 * @covers \JKingWeb\Arsse\Database<extended>
 * @covers \JKingWeb\Arsse\Misc\Query
 */
class TestSession extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\Test\Database\Setup;
    use \JKingWeb\Arsse\Test\Database\DriverSQLite3;
    use \JKingWeb\Arsse\Test\Database\SeriesSession;
}
