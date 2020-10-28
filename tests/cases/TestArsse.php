<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Lang;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;

/** @covers \JKingWeb\Arsse\Arsse */
class TestArsse extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData(false);
    }
    public function tearDown(): void {
        self::clearData();
    }

    public function testLoadExistingData(): void {
        $lang = Arsse::$lang = \Phake::mock(Lang::class);
        $db = Arsse::$db = \Phake::mock(Database::class);
        $user = Arsse::$user = \Phake::mock(User::class);
        $conf1 = Arsse::$conf = \Phake::mock(Conf::class);
        $conf2 = (new Conf)->import(['lang' => "test"]);
        Arsse::load($conf2);
        $this->assertSame($conf2, Arsse::$conf);
        $this->assertSame($lang, Arsse::$lang);
        $this->assertSame($db, Arsse::$db);
        $this->assertSame($user, Arsse::$user);
        \Phake::verify($lang)->set("test");
    }

    public function testLoadNewData(): void {
        if (!\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet() && !\JKingWeb\Arsse\Db\SQLite3\PDODriver::requirementsMet()) {
            $this->markTestSkipped("A functional SQLite interface is required for this test");
        }
        $conf = (new Conf)->import(['dbSQLite3File' => ":memory:"]);
        Arsse::load($conf);
        $this->assertInstanceOf(Conf::class, Arsse::$conf);
        $this->assertInstanceOf(Lang::class, Arsse::$lang);
        $this->assertInstanceOf(Database::class, Arsse::$db);
        $this->assertInstanceOf(User::class, Arsse::$user);
    }
}
