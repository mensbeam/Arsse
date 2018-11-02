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
use JKingWeb\Arsse\User\Driver;
use JKingWeb\Arsse\User\Internal\Driver as InternalDriver;
use Phake;

/** @covers \JKingWeb\Arsse\User */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    public static function setUpBeforeClass() {
        Arsse::$lang = new \JKingWeb\Arsse\Lang();
    }

    public function setUp() {
        $this->clearData();
        Arsse::$conf = new Conf;
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->begin->thenReturn(Phake::mock(\JKingWeb\Arsse\Db\Transaction::class));
        // create a mock user driver
        $this->drv = Phake::mock(Driver::class);
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
        Phake::when($this->drv)->auth->thenReturn(false);
        Phake::when($this->drv)->auth("john.doe@example.com", "secret")->thenReturn(true);
        Phake::when($this->drv)->auth("jane.doe@example.com", "superman")->thenReturn(true);
        Phake::when(Arsse::$db)->userExists("john.doe@example.com")->thenReturn(true);
        Phake::when(Arsse::$db)->userExists("jane.doe@example.com")->thenReturn(false);
        Phake::when(Arsse::$db)->userAdd->thenReturn("");
        $u = new User($this->drv);
        $this->assertSame($exp, $u->auth($user, $password));
        $this->assertNull($u->id);
        Phake::verify(Arsse::$db, Phake::times($exp ? 1 : 0))->userExists($user);
        Phake::verify(Arsse::$db, Phake::times($exp && $user == "jane.doe@example.com" ? 1 : 0))->userAdd($user, $password);
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
        Phake::when($this->drv)->authorize->thenReturn($authorized);
        Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
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
        Phake::when($this->drv)->authorize->thenReturn($authorized);
        Phake::when($this->drv)->userExists("john.doe@example.com")->thenReturn(true);
        Phake::when($this->drv)->userExists("jane.doe@example.com")->thenReturn(false);
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
        Phake::when($this->drv)->authorize->thenReturn($authorized);
        Phake::when($this->drv)->userAdd("john.doe@example.com", $this->anything())->thenThrow(new \JKingWeb\Arsse\User\Exception("alreadyExists"));
        Phake::when($this->drv)->userAdd("jane.doe@example.com", $this->anything())->thenReturnCallback(function($user, $pass) {
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
        Phake::when($this->drv)->authorize->thenReturn($authorized);
        Phake::when($this->drv)->userRemove("john.doe@example.com")->thenReturn(true);
        Phake::when($this->drv)->userRemove("jane.doe@example.com")->thenThrow(new \JKingWeb\Arsse\User\Exception("doesNotExist"));
        Phake::when(Arsse::$db)->userExists->thenReturn($exists);
        Phake::when(Arsse::$db)->userRemove->thenReturn(true);
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
            Phake::verify(Arsse::$db, Phake::times((int) $authorized))->userExists($user);
            Phake::verify(Arsse::$db, Phake::times((int) ($authorized && $exists)))->userRemove($user);
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
}
