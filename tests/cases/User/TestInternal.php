<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\AbstractException as Exception;
use JKingWeb\Arsse\User\Driver as DriverInterface;
use JKingWeb\Arsse\User\Internal\Driver;
use Phake;

/** @covers \JKingWeb\Arsse\User\Internal\Driver */
class TestInternal extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->begin->thenReturn(Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
    }

    public function testConstruct() {
        $this->assertInstanceOf(DriverInterface::class, new Driver);
    }

    public function testFetchDriverName() {
        $this->assertTrue(strlen(Driver::driverName()) > 0);
    }

    /**
     * @dataProvider provideAuthentication
     * @group slow
    */
    public function testAuthenticateAUser(bool $authorized, string $user, string $password, bool $exp) {
        if ($authorized) {
            Phake::when(Arsse::$db)->userPasswordGet("john.doe@example.com")->thenReturn('$2y$10$1zbqRJhxM8uUjeSBPp4IhO90xrqK0XjEh9Z16iIYEFRV4U.zeAFom'); // hash of "secret"
            Phake::when(Arsse::$db)->userPasswordGet("jane.doe@example.com")->thenReturn('$2y$10$bK1ljXfTSyc2D.NYvT.Eq..OpehLRXVbglW.23ihVuyhgwJCd.7Im'); // hash of "superman"
            Phake::when(Arsse::$db)->userPasswordGet("owen.hardy@example.com")->thenReturn("");
            Phake::when(Arsse::$db)->userPasswordGet("kira.nerys@example.com")->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        } else {
            Phake::when(Arsse::$db)->userPasswordGet->thenThrow(new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized"));
        }
        $this->assertSame($exp, (new Driver)->auth($user, $password));
    }

    public function provideAuthentication() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        $owen = "owen.hardy@example.com";
        $kira = "kira.nerys@example.com";
        return [
            [false, $john, "secret",     false],
            [false, $jane, "superman",   false],
            [false, $owen, "",           false],
            [false, $kira, "ashalla",    false],
            [true,  $john, "secret",     true],
            [true,  $jane, "superman",   true],
            [true,  $owen, "",           true],
            [true,  $kira, "ashalla",    false],
            [true,  $john, "top secret", false],
            [true,  $jane, "clark kent", false],
            [true,  $owen, "watchmaker", false],
            [true,  $kira, "singha",     false],
            [true,  $john, "",           false],
            [true,  $jane, "",           false],
            [true,  $kira, "",           false],
        ];
    }

    public function testAuthorizeAnAction() {
        Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertTrue((new Driver)->authorize("someone", "something"));
    }

    public function testListUsers() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        Phake::when(Arsse::$db)->userList->thenReturn([$john, $jane])->thenReturn([$jane, $john]);
        $driver = new Driver;
        $this->assertSame([$john, $jane], $driver->userList());
        $this->assertSame([$jane, $john], $driver->userList());
        Phake::verify(Arsse::$db, Phake::times(2))->userList;
    }

    public function testCheckThatAUserExists() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        Phake::when(Arsse::$db)->userExists($john)->thenReturn(true);
        Phake::when(Arsse::$db)->userExists($jane)->thenReturn(false);
        $driver = new Driver;
        $this->assertTrue($driver->userExists($john));
        Phake::verify(Arsse::$db)->userExists($john);
        $this->assertFalse($driver->userExists($jane));
        Phake::verify(Arsse::$db)->userExists($jane);
    }

    public function testAddAUser() {
        $john = "john.doe@example.com";
        Phake::when(Arsse::$db)->userAdd->thenReturnCallback(function($user, $pass) {
            return $pass;
        });
        $driver = new Driver;
        $this->assertNull($driver->userAdd($john));
        $this->assertNull($driver->userAdd($john, null));
        $this->assertSame("secret", $driver->userAdd($john, "secret"));
        Phake::verify(Arsse::$db)->userAdd($john, "secret");
        Phake::verify(Arsse::$db)->userAdd;
    }

    public function testRemoveAUser() {
        $john = "john.doe@example.com";
        Phake::when(Arsse::$db)->userRemove->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        $driver = new Driver;
        $this->assertTrue($driver->userRemove($john));
        Phake::verify(Arsse::$db, Phake::times(1))->userRemove($john);
        $this->assertException("doesNotExist", "User");
        try {
            $this->assertFalse($driver->userRemove($john));
        } finally {
            Phake::verify(Arsse::$db, Phake::times(2))->userRemove($john);
        }
    }

    public function testSetAPassword() {
        $john = "john.doe@example.com";
        Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertSame("superman", (new Driver)->userPasswordSet($john, "superman"));
        $this->assertSame(null, (new Driver)->userPasswordSet($john, null));
    }
}
