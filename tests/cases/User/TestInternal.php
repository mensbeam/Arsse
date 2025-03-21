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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\JKingWeb\Arsse\User\Internal\Driver::class)]
class TestInternal extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $d;

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
        $this->d = new Driver;
    }

    public function testConstruct(): void {
        $this->assertInstanceOf(DriverInterface::class, new Driver);
    }

    public function testFetchDriverName(): void {
        $this->assertTrue(strlen(Driver::driverName()) > 0);
    }

    #[DataProvider('provideAuthentication')]
    #[Group('slow')]
    public function testAuthenticateAUser(string $user, $password, bool $exp): void {
        \Phake::when(Arsse::$db)->userPasswordGet("john.doe@example.com")->thenReturn('$2y$10$1zbqRJhxM8uUjeSBPp4IhO90xrqK0XjEh9Z16iIYEFRV4U.zeAFom'); // hash of "secret"
        \Phake::when(Arsse::$db)->userPasswordGet("jane.doe@example.com")->thenReturn('$2y$10$bK1ljXfTSyc2D.NYvT.Eq..OpehLRXVbglW.23ihVuyhgwJCd.7Im'); // hash of "superman"
        \Phake::when(Arsse::$db)->userPasswordGet("owen.hardy@example.com")->thenReturn("");
        \Phake::when(Arsse::$db)->userPasswordGet("kira.nerys@example.com")->thenThrow(new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userPasswordGet("007@example.com")->thenReturn(null);
        $this->assertSame($exp, $this->d->auth($user, $password));
    }

    public static function provideAuthentication(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        $owen = "owen.hardy@example.com";
        $kira = "kira.nerys@example.com";
        $bond = "007@example.com";
        return [
            [$john, "secret",      true],
            [$jane, "superman",    true],
            [$owen, "",            true],
            [$kira, "ashalla",     false],
            [$john, "top secret",  false],
            [$jane, "clark kent",  false],
            [$owen, "watchmaker",  false],
            [$kira, "singha",      false],
            [$john, "",            false],
            [$jane, "",            false],
            [$kira, "",            false],
            [$bond, "for England", false],
            [$bond, "",            false],
        ];
    }

    public function testListUsers(): void {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        \Phake::when(Arsse::$db)->userList->thenReturn([$john, $jane])->thenReturn([$jane, $john]);
        $driver = $this->d;
        $this->assertSame([$john, $jane], $driver->userList());
        $this->assertSame([$jane, $john], $driver->userList());
        \Phake::verify(Arsse::$db, \Phake::times(2))->userList(\Phake::anyParameters());
    }

    public function testAddAUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userAdd->thenReturnCallback(function($user, $pass) {
            return $pass;
        });
        $driver = $this->d;
        $this->assertNull($driver->userAdd($john));
        $this->assertNull($driver->userAdd($john, null));
        $this->assertSame("secret", $driver->userAdd($john, "secret"));
        \Phake::verify(Arsse::$db)->userAdd($john, "secret");
        \Phake::verify(Arsse::$db)->userAdd(\Phake::anyParameters());
    }

    public function testRenameAUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertTrue($this->d->userRename($john, "jane.doe@example.com"));
        $this->assertFalse($this->d->userRename($john, $john));
        \Phake::verify(Arsse::$db, \Phake::times(2))->userExists($john);
    }

    public function testRenameAMissingUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->d->userRename($john, "jane.doe@example.com");
    }

    public function testRemoveAUser(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userRemove->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist"));
        $driver = $this->d;
        $this->assertTrue($driver->userRemove($john));
        \Phake::verify(Arsse::$db)->userRemove($john);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->assertFalse($driver->userRemove($john));
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times(2))->userRemove($john);
        }
    }

    public function testSetAPassword(): void {
        $john = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame("superman", $this->d->userPasswordSet($john, "superman"));
        $this->assertSame(null, $this->d->userPasswordSet($john, null));
        \Phake::verify(Arsse::$db, \Phake::never())->userPasswordSet(\Phake::anyParameters());
    }

    public function testSetAPasswordForAMssingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->d->userPasswordSet("john.doe@example.com", "secret");
    }

    public function testUnsetAPassword(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertTrue($this->d->userPasswordUnset("john.doe@example.com"));
        \Phake::verify(Arsse::$db, \Phake::never())->userPasswordSet(\Phake::anyParameters());
    }

    public function testUnsetAPasswordForAMssingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->d->userPasswordUnset("john.doe@example.com");
    }

    public function testGetUserProperties(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame([], $this->d->userPropertiesGet("john.doe@example.com"));
        \Phake::verify(Arsse::$db)->userExists("john.doe@example.com");
    }

    public function testGetPropertiesForAMissingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->d->userPropertiesGet("john.doe@example.com");
        } finally {
            \Phake::verify(Arsse::$db)->userExists("john.doe@example.com");
        }
    }

    public function testSetUserProperties(): void {
        $in = ['admin' => true];
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($in, $this->d->userPropertiesSet("john.doe@example.com", $in));
        \Phake::verify(Arsse::$db)->userExists("john.doe@example.com");
    }

    public function testSetPropertiesForAMissingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->d->userPropertiesSet("john.doe@example.com", ['admin' => true]);
        } finally {
            \Phake::verify(Arsse::$db)->userExists("john.doe@example.com");
        }
    }
}
