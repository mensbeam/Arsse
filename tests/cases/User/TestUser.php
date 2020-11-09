<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\AbstractException as Exception;
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

    /** @dataProvider provideUserList */
    public function testListUsers($exp): void {
        $u = new User($this->drv);
        \Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
        $this->assertSame($exp, $u->list());
    }

    public function provideUserList(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [[$john, $jane]],
        ];
    }

    /** @dataProvider provideAdditions */
    public function testAddAUser(string $user, $password, $exp): void {
        $u = new User($this->drv);
        \Phake::when($this->drv)->userAdd("john.doe@example.com", $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("alreadyExists"));
        \Phake::when($this->drv)->userAdd("jane.doe@example.com", $this->anything())->thenReturnCallback(function($user, $pass) {
            return $pass ?? "random password";
        });
        if ($exp instanceof Exception) {
            $this->assertException("alreadyExists", "User");
        }
        $this->assertSame($exp, $u->add($user, $password));
    }

    /** @dataProvider provideAdditions */
    public function testAddAUserWithARandomPassword(string $user, $password, $exp): void {
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($this->drv)->userAdd($this->anything(), $this->isNull())->thenReturn(null);
        \Phake::when($this->drv)->userAdd("john.doe@example.com", $this->logicalNot($this->isNull()))->thenThrow(new \JKingWeb\Arsse\User\Exception("alreadyExists"));
        \Phake::when($this->drv)->userAdd("jane.doe@example.com", $this->logicalNot($this->isNull()))->thenReturnCallback(function($user, $pass) {
            return $pass;
        });
        if ($exp instanceof Exception) {
            $this->assertException("alreadyExists", "User");
            $calls = 2;
        } else {
            $calls = 4;
        }
        try {
            $pass1 = $u->add($user, null);
            $pass2 = $u->add($user, null);
            $this->assertNotEquals($pass1, $pass2);
        } finally {
            \Phake::verify($this->drv, \Phake::times($calls))->userAdd;
            \Phake::verify($u, \Phake::times($calls / 2))->generatePassword;
        }
    }

    public function provideAdditions(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [$john, "secret",   new \JKingWeb\Arsse\User\Exception("alreadyExists")],
            [$jane, "superman", "superman"],
            [$jane, null,       "random password"],
        ];
    }

    /** @dataProvider provideRemovals */
    public function testRemoveAUser(string $user, bool $exists, $exp): void {
        $u = new User($this->drv);
        \Phake::when($this->drv)->userRemove("john.doe@example.com")->thenReturn(true);
        \Phake::when($this->drv)->userRemove("jane.doe@example.com")->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        \Phake::when(Arsse::$db)->userRemove->thenReturn(true);
        if ($exp instanceof Exception) {
            $this->assertException("doesNotExist", "User");
        }
        try {
            $this->assertSame($exp, $u->remove($user));
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times(1))->userExists($user);
            \Phake::verify(Arsse::$db, \Phake::times((int) $exists))->userRemove($user);
        }
    }

    public function provideRemovals(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [$john, true,  true],
            [$john, false, true],
            [$jane, true,  new \JKingWeb\Arsse\User\Exception("doesNotExist")],
            [$jane, false, new \JKingWeb\Arsse\User\Exception("doesNotExist")],
        ];
    }

    /** @dataProvider providePasswordChanges */
    public function testChangeAPassword(string $user, $password, bool $exists, $exp): void {
        $u = new User($this->drv);
        \Phake::when($this->drv)->userPasswordSet("john.doe@example.com", $this->anything(), $this->anything())->thenReturnCallback(function($user, $pass, $old) {
            return $pass ?? "random password";
        });
        \Phake::when($this->drv)->userPasswordSet("jane.doe@example.com", $this->anything(), $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        if ($exp instanceof Exception) {
            $this->assertException("doesNotExist", "User");
            $calls = 0;
        } else {
            $calls = 1;
        }
        try {
            $this->assertSame($exp, $u->passwordSet($user, $password));
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times($calls))->userExists($user);
            \Phake::verify(Arsse::$db, \Phake::times($exists ? $calls : 0))->userPasswordSet($user, $password ?? "random password", null);
        }
    }

    /** @dataProvider providePasswordChanges */
    public function testChangeAPasswordToARandomPassword(string $user, $password, bool $exists, $exp): void {
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($this->drv)->userPasswordSet($this->anything(), $this->isNull(), $this->anything())->thenReturn(null);
        \Phake::when($this->drv)->userPasswordSet("john.doe@example.com", $this->logicalNot($this->isNull()), $this->anything())->thenReturnCallback(function($user, $pass, $old) {
            return $pass ?? "random password";
        });
        \Phake::when($this->drv)->userPasswordSet("jane.doe@example.com", $this->logicalNot($this->isNull()), $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        if ($exp instanceof Exception) {
            $this->assertException("doesNotExist", "User");
            $calls = 2;
        } else {
            $calls = 4;
        }
        try {
            $pass1 = $u->passwordSet($user, null);
            $pass2 = $u->passwordSet($user, null);
            $this->assertNotEquals($pass1, $pass2);
        } finally {
            \Phake::verify($this->drv, \Phake::times($calls))->userPasswordSet;
            \Phake::verify($u, \Phake::times($calls / 2))->generatePassword;
            \Phake::verify(Arsse::$db, \Phake::times($calls == 4 ? 2 : 0))->userExists($user);
            if ($calls == 4) {
                \Phake::verify(Arsse::$db, \Phake::times($exists ? 1 : 0))->userPasswordSet($user, $pass1, null);
                \Phake::verify(Arsse::$db, \Phake::times($exists ? 1 : 0))->userPasswordSet($user, $pass2, null);
            } else {
                \Phake::verify(Arsse::$db, \Phake::times(0))->userPasswordSet;
            }
        }
    }

    public function providePasswordChanges(): iterable {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [$john, "superman", true,  "superman"],
            [$john, null,       true,  "random password"],
            [$john, "superman", false, "superman"],
            [$john, null,       false, "random password"],
            [$jane, "secret",   true,  new \JKingWeb\Arsse\User\Exception("doesNotExist")],
        ];
    }

    /** @dataProvider providePasswordClearings */
    public function testClearAPassword(bool $exists, string $user, $exp): void {
        \Phake::when($this->drv)->userPasswordUnset->thenReturn(true);
        \Phake::when($this->drv)->userPasswordUnset("jane.doe@example.net", null)->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        $u = new User($this->drv);
        try {
            if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
                $this->assertException($exp);
                $u->passwordUnset($user);
            } else {
                $this->assertSame($exp, $u->passwordUnset($user));
            }
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times((int) ($exists && is_bool($exp))))->userPasswordSet($user, null);
        }
    }

    public function providePasswordClearings(): iterable {
        $missing = new \JKingWeb\Arsse\User\Exception("doesNotExist");
        return [
            [true,  "jane.doe@example.com", true],
            [true,  "john.doe@example.com", true],
            [true,  "jane.doe@example.net", $missing],
            [false, "jane.doe@example.com", true],
            [false, "john.doe@example.com", true],
            [false, "jane.doe@example.net", $missing],
        ];
    }
}
