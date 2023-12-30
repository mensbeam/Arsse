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
        parent::setUp();
        self::setConf();
        // create a mock database interface
        $this->dbMock = $this->mock(Database::class);
        $this->dbMock->begin->returns($this->mock(\JKingWeb\Arsse\Db\Transaction::class));
    }

    protected function prepTest(): Driver {
        Arsse::$db = $this->dbMock->get();
        return new Driver;
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
    public function testAuthenticateAUser(string $user, $password, bool $exp): void {
        $this->dbMock->userPasswordGet->with("john.doe@example.com")->returns('$2y$10$1zbqRJhxM8uUjeSBPp4IhO90xrqK0XjEh9Z16iIYEFRV4U.zeAFom'); // hash of "secret"
        $this->dbMock->userPasswordGet->with("jane.doe@example.com")->returns('$2y$10$bK1ljXfTSyc2D.NYvT.Eq..OpehLRXVbglW.23ihVuyhgwJCd.7Im'); // hash of "superman"
        $this->dbMock->userPasswordGet->with("owen.hardy@example.com")->returns("");
        $this->dbMock->userPasswordGet->with("kira.nerys@example.com")->throws(new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist"));
        $this->dbMock->userPasswordGet->with("007@example.com")->returns(null);
        $this->assertSame($exp, $this->prepTest()->auth($user, $password));
    }

    public function provideAuthentication(): iterable {
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
        $this->dbMock->userList->returns([$john, $jane])->returns([$jane, $john]);
        $driver = $this->prepTest();
        $this->assertSame([$john, $jane], $driver->userList());
        $this->assertSame([$jane, $john], $driver->userList());
        $this->dbMock->userList->times(2)->called();
    }

    public function testAddAUser(): void {
        $john = "john.doe@example.com";
        $this->dbMock->userAdd->does(function($user, $pass) {
            return $pass;
        });
        $driver = $this->prepTest();
        $this->assertNull($driver->userAdd($john));
        $this->assertNull($driver->userAdd($john, null));
        $this->assertSame("secret", $driver->userAdd($john, "secret"));
        $this->dbMock->userAdd->calledWith($john, "secret");
        $this->dbMock->userAdd->called();
    }

    public function testRenameAUser(): void {
        $john = "john.doe@example.com";
        $this->dbMock->userExists->returns(true);
        $this->assertTrue($this->prepTest()->userRename($john, "jane.doe@example.com"));
        $this->assertFalse($this->prepTest()->userRename($john, $john));
        $this->dbMock->userExists->times(2)->calledWith($john);
    }

    public function testRenameAMissingUser(): void {
        $john = "john.doe@example.com";
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->prepTest()->userRename($john, "jane.doe@example.com");
    }

    public function testRemoveAUser(): void {
        $john = "john.doe@example.com";
        $this->dbMock->userRemove->returns(true)->throws(new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist"));
        $driver = $this->prepTest();
        $this->assertTrue($driver->userRemove($john));
        $this->dbMock->userRemove->calledWith($john);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->assertFalse($driver->userRemove($john));
        } finally {
            $this->dbMock->userRemove->times(2)->calledWith($john);
        }
    }

    public function testSetAPassword(): void {
        $john = "john.doe@example.com";
        $this->dbMock->userExists->returns(true);
        $this->assertSame("superman", $this->prepTest()->userPasswordSet($john, "superman"));
        $this->assertSame(null, $this->prepTest()->userPasswordSet($john, null));
        $this->dbMock->userPasswordSet->never()->called();
    }

    public function testSetAPasswordForAMssingUser(): void {
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->prepTest()->userPasswordSet("john.doe@example.com", "secret");
    }

    public function testUnsetAPassword(): void {
        $this->dbMock->userExists->returns(true);
        $this->assertTrue($this->prepTest()->userPasswordUnset("john.doe@example.com"));
        $this->dbMock->userPasswordSet->never()->called();
    }

    public function testUnsetAPasswordForAMssingUser(): void {
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->prepTest()->userPasswordUnset("john.doe@example.com");
    }

    public function testGetUserProperties(): void {
        $this->dbMock->userExists->returns(true);
        $this->assertSame([], $this->prepTest()->userPropertiesGet("john.doe@example.com"));
        $this->dbMock->userExists->calledWith("john.doe@example.com");
    }

    public function testGetPropertiesForAMissingUser(): void {
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->prepTest()->userPropertiesGet("john.doe@example.com");
        } finally {
            $this->dbMock->userExists->calledWith("john.doe@example.com");
        }
    }

    public function testSetUserProperties(): void {
        $in = ['admin' => true];
        $this->dbMock->userExists->returns(true);
        $this->assertSame($in, $this->prepTest()->userPropertiesSet("john.doe@example.com", $in));
        $this->dbMock->userExists->calledWith("john.doe@example.com");
    }

    public function testSetPropertiesForAMissingUser(): void {
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $this->prepTest()->userPropertiesSet("john.doe@example.com", ['admin' => true]);
        } finally {
            $this->dbMock->userExists->calledWith("john.doe@example.com");
        }
    }
}
