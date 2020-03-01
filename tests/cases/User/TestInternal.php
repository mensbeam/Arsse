<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User\Driver as DriverInterface;
use JKingWeb\Arsse\User\Internal\Driver;

/** @covers \JKingWeb\Arsse\User\Internal\Driver */
class TestInternal extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
    }

    public function testConstruct(): void {
        $this->assertInstanceOf(DriverInterface::class, new Driver);
    }

    public function testFetchDriverName(): void {
        $this->assertTrue(strlen(Driver::driverName()) > 0);
    }

    /**
     * @dataProvider provideAuthentication
     * @group slow
     */
    public function testAuthenticateAUser(bool $authorized, string $user, $password, bool $exp): void {
        if ($authorized) {
            \Phake::when(Arsse::$db)->userPasswordGet("john.doe@example.com")->thenReturn('$2y$10$1zbqRJhxM8uUjeSBPp4IhO90xrqK0XjEh9Z16iIYEFRV4U.zeAFom'); // hash of "secret"
            \Phake::when(Arsse::$db)->userPasswordGet("jane.doe@example.com")->thenReturn('$2y$10$bK1ljXfTSyc2D.NYvT.Eq..OpehLRXVbglW.23ihVuyhgwJCd.7Im'); // hash of "superman"
            \Phake::when(Arsse::$db)->userPasswordGet("owen.hardy@example.com")->thenReturn("");
            \Phake::when(Arsse::$db)->userPasswordGet("kira.nerys@example.com")->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
            \Phake::when(Arsse::$db)->userPasswordGet("007@example.com")->thenReturn(null);
        } else {
            \Phake::when(Arsse::$db)->userPasswordGet->thenThrow(new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized"));
        }
        $this->assertSame($exp, (new Driver)->auth($user, $password));
    }

    public function provideAuthentication(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        $owen = "owen.hardy@example.com";
        $kira = "kira.nerys@example.com";
        $bond = "007@example.com";
        return [
            [false, $john, "secret",      false],
            [false, $jane, "superman",    false],
            [false, $owen, "",            false],
            [false, $kira, "ashalla",     false],
            [false, $bond, "",            false],
            [true,  $john, "secret",      true],
            [true,  $jane, "superman",    true],
            [true,  $owen, "",            true],
            [true,  $kira, "ashalla",     false],
            [true,  $john, "top secret",  false],
            [true,  $jane, "clark kent",  false],
            [true,  $owen, "watchmaker",  false],
            [true,  $kira, "singha",      false],
            [true,  $john, "",            false],
            [true,  $jane, "",            false],
            [true,  $kira, "",            false],
            [true,  $bond, "for England", false],
            [true,  $bond, "",            false],
        ];
    }

    public function testAuthorizeAnAction(): void {
        \Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertTrue((new Driver)->authorize("someone", "something"));
    }

    public function testListUsers(): void {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        \Phake::when(Arsse::$db)->userList->thenReturn([$john, $jane])->thenReturn([$jane, $john]);
        $driver = new Driver;
        $this->assertSame([$john, $jane], $driver->userList());
        $this->assertSame([$jane, $john], $driver->userList());
        \Phake::verify(Arsse::$db, \Phake::times(2))->userList;
    }

    public function testCheckThatAUserExists(): void {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        \Phake::when(Arsse::$db)->userExists($john)->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists($jane)->thenReturn(false);
        $driver = new Driver;
        $this->assertTrue($driver->userExists($john));
        \Phake::verify(Arsse::$db)->userExists($john);
        $this->assertFalse($driver->userExists($jane));
        \Phake::verify(Arsse::$db)->userExists($jane);
    }

    public function testAddAUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userAdd->thenReturnCallback(function($user, $pass) {
            return $pass;
        });
        $driver = new Driver;
        $this->assertNull($driver->userAdd($john));
        $this->assertNull($driver->userAdd($john, null));
        $this->assertSame("secret", $driver->userAdd($john, "secret"));
        \Phake::verify(Arsse::$db)->userAdd($john, "secret");
        \Phake::verify(Arsse::$db)->userAdd;
    }

    public function testRemoveAUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userRemove->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        $driver = new Driver;
        $this->assertTrue($driver->userRemove($john));
        \Phake::verify(Arsse::$db, \Phake::times(1))->userRemove($john);
        $this->assertException("doesNotExist", "User");
        try {
            $this->assertFalse($driver->userRemove($john));
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times(2))->userRemove($john);
        }
    }

    public function testSetAPassword(): void {
        $john = "john.doe@example.com";
        \Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertSame("superman", (new Driver)->userPasswordSet($john, "superman"));
        $this->assertSame(null, (new Driver)->userPasswordSet($john, null));
    }

    public function testUnsetAPassword(): void {
        $drv = \Phake::partialMock(Driver::class);
        \Phake::when($drv)->userExists->thenReturn(true);
        \Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertTrue($drv->userPasswordUnset("john.doe@example.com"));
    }

    public function testUnsetAPasswordForAMssingUser(): void {
        $drv = \Phake::partialMock(Driver::class);
        \Phake::when($drv)->userExists->thenReturn(false);
        \Phake::verifyNoFurtherInteraction(Arsse::$db);
        $this->assertException("doesNotExist", "User");
        $drv->userPasswordUnset("john.doe@example.com");
    }
}
