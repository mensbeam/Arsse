<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\ExceptionInput;
use JKingWeb\Arsse\User\Driver;

/** @covers \JKingWeb\Arsse\User */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
        // create a mock user driver
        $this->drv = \Phake::mock(Driver::class);
    }

    public function tearDown(): void {
        \Phake::verifyNoOtherInteractions($this->drv);
        \Phake::verifyNoOtherInteractions(Arsse::$db);
    }

    public function testConstruct(): void {
        $this->assertInstanceOf(User::class, new User($this->drv));
        $this->assertInstanceOf(User::class, new User);
    }

    public function testConversionToString(): void {
        $u = new User;
        $u->id = "john.doe@example.com";
        $this->assertSame("john.doe@example.com", (string) $u);
        $u->id = null;
        $this->assertSame("", (string) $u);
    }

    /** @dataProvider provideAuthentication */
    public function testAuthenticateAUser(bool $preAuth, string $user, string $password, bool $exp): void {
        Arsse::$conf->userPreAuth = $preAuth;
        \Phake::when($this->drv)->auth->thenReturn(false);
        \Phake::when($this->drv)->auth("john.doe@example.com", "secret")->thenReturn(true);
        \Phake::when($this->drv)->auth("jane.doe@example.com", "superman")->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists("john.doe@example.com")->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists("jane.doe@example.com")->thenReturn(false);
        \Phake::when(Arsse::$db)->userAdd->thenReturn("");
        $u = new User($this->drv);
        $this->assertSame($exp, $u->auth($user, $password));
        $this->assertNull($u->id);
        \Phake::verify($this->drv, \Phake::times((int) !$preAuth))->auth($user, $password);
        \Phake::verify(Arsse::$db, \Phake::times($exp ? 1 : 0))->userExists($user);
        \Phake::verify(Arsse::$db, \Phake::times($exp && $user === "jane.doe@example.com" ? 1 : 0))->userAdd($user, $password);
    }

    public function provideAuthentication(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, "secret",   true],
            [false, $john, "superman", false],
            [false, $jane, "secret",   false],
            [false, $jane, "superman", true],
            [true,  $john, "secret",   true],
            [true,  $john, "superman", true],
            [true,  $jane, "secret",   true],
            [true,  $jane, "superman", true],
        ];
    }

    public function testListUsers(): void {
        $exp = ["john.doe@example.com", "jane.doe@example.com"];
        $u = new User($this->drv);
        \Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
        $this->assertSame($exp, $u->list());
        \Phake::verify($this->drv)->userList();
    }

    public function testAddAUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testAddAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify(Arsse::$db)->userAdd($user, $pass);
    }

    public function testAddADuplicateUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify($this->drv)->userAdd($user, $pass);
        }
    }

    public function testAddADuplicateUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify(Arsse::$db)->userAdd($user, null);
            \Phake::verify($this->drv)->userAdd($user, $pass);
        }
    }

    public function testAddAnInvalidUser(): void {
        $user = "john:doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionInput("invalidUsername"));
        $this->assertException("invalidUsername", "User", "ExceptionInput");
        $u->add($user, $pass);
    }

    public function testAddAUserWithARandomPassword(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($u)->generatePassword->thenReturn($pass);
        \Phake::when($this->drv)->userAdd->thenReturn(null)->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($pass, $u->add($user));
        \Phake::verify($this->drv)->userAdd($user, null);
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testRemoveAUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userRemove->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertTrue($u->remove($user));
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify(Arsse::$db)->userRemove($user);
        \Phake::verify($this->drv)->userRemove($user);
    }

    public function testRemoveAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userRemove->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertTrue($u->remove($user));
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify($this->drv)->userRemove($user);
    }

    public function testRemoveAMissingUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userRemove->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->remove($user);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify(Arsse::$db)->userRemove($user);
            \Phake::verify($this->drv)->userRemove($user);
        }
    }

    public function testRemoveAMissingUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userRemove->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->remove($user);
        } finally {
            \Phake::verify(Arsse::$db)->userExists($user);
            \Phake::verify($this->drv)->userRemove($user);
        }
    }

    public function testSetAPassword(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($pass, $u->passwordSet($user, $pass));
        \Phake::verify($this->drv)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->sessionDestroy($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testSetARandomPassword(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($u)->generatePassword->thenReturn($pass);
        \Phake::when($this->drv)->userPasswordSet->thenReturn(null)->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($pass, $u->passwordSet($user, null));
        \Phake::verify($this->drv)->userPasswordSet($user, null, null);
        \Phake::verify($this->drv)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->sessionDestroy($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testSetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertSame($pass, $u->passwordSet($user, $pass));
        \Phake::verify($this->drv)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testSetARandomPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($u)->generatePassword->thenReturn($pass);
        \Phake::when($this->drv)->userPasswordSet->thenReturn(null)->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertSame($pass, $u->passwordSet($user, null));
        \Phake::verify($this->drv)->userPasswordSet($user, null, null);
        \Phake::verify($this->drv)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testSetARandomPasswordForAMissingUser(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($u)->generatePassword->thenReturn($pass);
        \Phake::when($this->drv)->userPasswordSet->thenThrow(new ExceptionConflict("doesNotExist"));
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->passwordSet($user, null);
        } finally {
            \Phake::verify($this->drv)->userPasswordSet($user, null, null);
        }
    }

    public function testUnsetAPassword(): void {
        $user = "john.doe@example.com";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertTrue($u->passwordUnset($user));
        \Phake::verify($this->drv)->userPasswordUnset($user, null);
        \Phake::verify(Arsse::$db)->userPasswordSet($user, null);
        \Phake::verify(Arsse::$db)->sessionDestroy($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testUnsetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertTrue($u->passwordUnset($user));
        \Phake::verify($this->drv)->userPasswordUnset($user, null);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testUnsetAPasswordForAMissingUser(): void {
        $user = "john.doe@example.com";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordUnset->thenThrow(new ExceptionConflict("doesNotExist"));
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->passwordUnset($user);
        } finally {
            \Phake::verify($this->drv)->userPasswordUnset($user, null);
        }
    }

    /** @dataProvider provideProperties */
    public function testGetThePropertiesOfAUser(array $exp, array $base, array $extra): void {
        $user = "john.doe@example.com";
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPropertiesGet->thenReturn($extra);
        \Phake::when(Arsse::$db)->userPropertiesGet->thenReturn($base);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($exp, $u->propertiesGet($user));
        \Phake::verify($this->drv)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function provideProperties(): iterable {
        $defaults = ['num' => 1, 'admin' => false, 'lang' => null, 'tz' => "Etc/UTC", 'sort_asc' => false];
        return [
            [$defaults, $defaults, []],
            [$defaults, $defaults, ['num' => 2112, 'blah' => "bloo"]],
            [['num' => 1, 'admin' => true, 'lang' => "fr", 'tz' => "America/Toronto", 'sort_asc' => true], $defaults, ['admin' => true, 'lang' => "fr", 'tz' => "America/Toronto", 'sort_asc' => true]],
            [['num' => 1, 'admin' => true, 'lang' => null, 'tz' => "America/Toronto", 'sort_asc' => true], ['num' => 1, 'admin' => true, 'lang' => "fr", 'tz' => "America/Toronto", 'sort_asc' => true], ['lang' => null]],
        ];
    }
}
