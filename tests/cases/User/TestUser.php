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
    public function setUp() {
        self::clearData();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
        // create a mock user driver
        $this->drv = \Phake::mock(Driver::class);
    }

    public function testListDrivers() {
        $exp = [
            'JKingWeb\\Arsse\\User\\Internal\\Driver' => Arsse::$lang->msg("Driver.User.Internal.Name"),
        ];
        $this->assertArraySubset($exp, User::driverList());
    }

    public function testConstruct() {
        $this->assertInstanceOf(User::class, new User($this->drv));
        $this->assertInstanceOf(User::class, new User);
    }

    public function testConversionToString() {
        $u = new User;
        $u->id = "john.doe@example.com";
        $this->assertSame("john.doe@example.com", (string) $u);
        $u->id = null;
        $this->assertSame("", (string) $u);
    }

    /** @dataProvider provideAuthentication */
    public function testAuthenticateAUser(bool $preAuth, string $user, string $password, bool $exp) {
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

    public function provideAuthentication() {
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
    public function testListUsers(bool $authorized, $exp) {
        $u = new User($this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
        if ($exp instanceof Exception) {
            $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        }
        $this->assertSame($exp, $u->list());
    }

    public function provideUserList() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [true,  [$john, $jane]],
        ];
    }

    /** @dataProvider provideExistence */
    public function testCheckThatAUserExists(bool $authorized, string $user, $exp) {
        $u = new User($this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userExists("john.doe@example.com")->thenReturn(true);
        \Phake::when($this->drv)->userExists("jane.doe@example.com")->thenReturn(false);
        if ($exp instanceof Exception) {
            $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        }
        $this->assertSame($exp, $u->exists($user));
    }

    public function provideExistence() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $jane, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [true,  $john, true],
            [true,  $jane, false],
        ];
    }

    /** @dataProvider provideAdditions */
    public function testAddAUser(bool $authorized, string $user, $password, $exp) {
        $u = new User($this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userAdd("john.doe@example.com", $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("alreadyExists"));
        \Phake::when($this->drv)->userAdd("jane.doe@example.com", $this->anything())->thenReturnCallback(function($user, $pass) {
            return $pass ?? "random password";
        });
        if ($exp instanceof Exception) {
            if ($exp instanceof \JKingWeb\Arsse\User\ExceptionAuthz) {
                $this->assertException("notAuthorized", "User", "ExceptionAuthz");
            } else {
                $this->assertException("alreadyExists", "User");
            }
        }
        $this->assertSame($exp, $u->add($user, $password));
    }

    /** @dataProvider provideAdditions */
    public function testAddAUserWithARandomPassword(bool $authorized, string $user, $password, $exp) {
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userAdd($this->anything(), $this->isNull())->thenReturn(null);
        \Phake::when($this->drv)->userAdd("john.doe@example.com", $this->logicalNot($this->isNull()))->thenThrow(new \JKingWeb\Arsse\User\Exception("alreadyExists"));
        \Phake::when($this->drv)->userAdd("jane.doe@example.com", $this->logicalNot($this->isNull()))->thenReturnCallback(function($user, $pass) {
            return $pass;
        });
        if ($exp instanceof Exception) {
            if ($exp instanceof \JKingWeb\Arsse\User\ExceptionAuthz) {
                $this->assertException("notAuthorized", "User", "ExceptionAuthz");
                $calls = 0;
            } else {
                $this->assertException("alreadyExists", "User");
                $calls = 2;
            }
        } else {
            $calls =  4;
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

    public function provideAdditions() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, "secret",   new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $jane, "superman", new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [true,  $john, "secret",   new \JKingWeb\Arsse\User\Exception("alreadyExists")],
            [true,  $jane, "superman", "superman"],
            [true,  $jane, null,       "random password"],
        ];
    }

    /** @dataProvider provideRemovals */
    public function testRemoveAUser(bool $authorized, string $user, bool $exists, $exp) {
        $u = new User($this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userRemove("john.doe@example.com")->thenReturn(true);
        \Phake::when($this->drv)->userRemove("jane.doe@example.com")->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        \Phake::when(Arsse::$db)->userRemove->thenReturn(true);
        if ($exp instanceof Exception) {
            if ($exp instanceof \JKingWeb\Arsse\User\ExceptionAuthz) {
                $this->assertException("notAuthorized", "User", "ExceptionAuthz");
            } else {
                $this->assertException("doesNotExist", "User");
            }
        }
        try {
            $this->assertSame($exp, $u->remove($user));
        } finally {
            \Phake::verify(Arsse::$db, \Phake::times((int) $authorized))->userExists($user);
            \Phake::verify(Arsse::$db, \Phake::times((int) ($authorized && $exists)))->userRemove($user);
        }
    }

    public function provideRemovals() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, true,  new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $john, false, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $jane, true,  new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $jane, false, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [true,  $john, true,  true],
            [true,  $john, false, true],
            [true,  $jane, true,  new \JKingWeb\Arsse\User\Exception("doesNotExist")],
            [true,  $jane, false, new \JKingWeb\Arsse\User\Exception("doesNotExist")],
        ];
    }

    /** @dataProvider providePasswordChanges */
    public function testChangeAPassword(bool $authorized, string $user, $password, bool $exists, $exp) {
        $u = new User($this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userPasswordSet("john.doe@example.com", $this->anything(), $this->anything())->thenReturnCallback(function($user, $pass, $old) {
            return $pass ?? "random password";
        });
        \Phake::when($this->drv)->userPasswordSet("jane.doe@example.com", $this->anything(), $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        if ($exp instanceof Exception) {
            if ($exp instanceof \JKingWeb\Arsse\User\ExceptionAuthz) {
                $this->assertException("notAuthorized", "User", "ExceptionAuthz");
            } else {
                $this->assertException("doesNotExist", "User");
            }
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
    public function testChangeAPasswordToARandomPassword(bool $authorized, string $user, $password, bool $exists, $exp) {
        $u = \Phake::partialMock(User::class, $this->drv);
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
        \Phake::when($this->drv)->userPasswordSet($this->anything(), $this->isNull(), $this->anything())->thenReturn(null);
        \Phake::when($this->drv)->userPasswordSet("john.doe@example.com", $this->logicalNot($this->isNull()), $this->anything())->thenReturnCallback(function($user, $pass, $old) {
            return $pass ?? "random password";
        });
        \Phake::when($this->drv)->userPasswordSet("jane.doe@example.com", $this->logicalNot($this->isNull()), $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        if ($exp instanceof Exception) {
            if ($exp instanceof \JKingWeb\Arsse\User\ExceptionAuthz) {
                $this->assertException("notAuthorized", "User", "ExceptionAuthz");
                $calls = 0;
            } else {
                $this->assertException("doesNotExist", "User");
                $calls = 2;
            }
        } else {
            $calls =  4;
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

    public function providePasswordChanges() {
        $john = "john.doe@example.com";
        $jane = "jane.doe@example.com";
        return [
            [false, $john, "secret",   true,  new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [false, $jane, "superman", false, new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized")],
            [true,  $john, "superman", true,  "superman"],
            [true,  $john, null,       true,  "random password"],
            [true,  $john, "superman", false, "superman"],
            [true,  $john, null,       false, "random password"],
            [true,  $jane, "secret",   true,  new \JKingWeb\Arsse\User\Exception("doesNotExist")],
        ];
    }

    /** @dataProvider providePasswordClearings */
    public function testClearAPassword(bool $authorized, bool $exists, string $user, $exp) {
        \Phake::when($this->drv)->authorize->thenReturn($authorized);
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
            \Phake::verify(Arsse::$db, \Phake::times((int) ($authorized && $exists && is_bool($exp))))->userPasswordSet($user, null);
        }
    }

    public function providePasswordClearings() {
        $forbidden = new \JKingWeb\Arsse\User\ExceptionAuthz("notAuthorized");
        $missing = new \JKingWeb\Arsse\User\Exception("doesNotExist");
        return [
            [false, true,  "jane.doe@example.com", $forbidden],
            [false, true,  "john.doe@example.com", $forbidden],
            [false, true,  "jane.doe@example.net", $forbidden],
            [false, false, "jane.doe@example.com", $forbidden],
            [false, false, "john.doe@example.com", $forbidden],
            [false, false, "jane.doe@example.net", $forbidden],
            [true,  true,  "jane.doe@example.com", true],
            [true,  true,  "john.doe@example.com", true],
            [true,  true,  "jane.doe@example.net", $missing],
            [true,  false, "jane.doe@example.com", true],
            [true,  false, "john.doe@example.com", true],
            [true,  false, "jane.doe@example.net", $missing],
        ];
    }
}
