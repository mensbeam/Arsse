<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\ExceptionInput;
use JKingWeb\Arsse\User\Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\User::class)]
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;

    public function setUp(): void {
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

    public function testStartATransaction(): void {
        $u = new User($this->drv);
        $this->assertInstanceOf(Transaction::class, $u->begin());
        \Phake::verify(Arsse::$db)->begin();
    }

    public function testGeneratePasswords(): void {
        $u = new User($this->drv);
        $pass1 = $u->generatePassword();
        $pass2 = $u->generatePassword();
        $this->assertNotEquals($pass1, $pass2);
    }


    #[DataProvider('provideAuthentication')]
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
        \Phake::verify($this->drv, \Phake::times((int) !$preAuth))->auth($this->anything(), $this->anything());
        \Phake::verify(Arsse::$db, \Phake::times($exp ? 1 : 0))->userExists($user);
        \Phake::verify(Arsse::$db, \Phake::times($exp && $user === "jane.doe@example.com" ? 1 : 0))->userAdd($user, $password);
    }

    public static function provideAuthentication(): iterable {
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
        \Phake::when($this->drv)->userList->thenReturn(["john.doe@example.com", "jane.doe@example.com"]);
        $u = new User($this->drv);
        $this->assertSame($exp, $u->list());
        \Phake::verify($this->drv)->userList();
    }

    public function testLookUpAUserByNumber(): void {
        $exp = "john.doe@example.com";
        \Phake::when(Arsse::$db)->userLookup->thenReturn($exp);
        $u = new User($this->drv);
        $this->assertSame($exp, $u->lookup(2112));
        \Phake::verify(Arsse::$db)->userLookup(2112);
    }

    public function testAddAUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testAddAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        \Phake::when($this->drv)->userAdd->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
        $this->assertSame($pass, $u->add($user, $pass));
        \Phake::verify($this->drv)->userAdd($user, $pass);
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify(Arsse::$db)->userAdd($user, $pass);
    }

    public function testAddADuplicateUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
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
        \Phake::when($this->drv)->userAdd->thenThrow(new ExceptionConflict("alreadyExists"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
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
        $u = new User($this->drv);
        $this->assertException("invalidUsername", "User", "ExceptionInput");
        $u->add("john:doe@example.com", "secret");
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

    public function testRenameAUser(): void {
        $tr = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($tr);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        \Phake::when(Arsse::$db)->userAdd->thenReturn(true);
        \Phake::when(Arsse::$db)->userRename->thenReturn(true);
        \Phake::when($this->drv)->userRename->thenReturn(true);
        $u = new User($this->drv);
        $old = "john.doe@example.com";
        $new = "jane.doe@example.com";
        $this->assertTrue($u->rename($old, $new));
        \Phake::inOrder(
            \Phake::verify($this->drv)->userRename($old, $new),
            \Phake::verify(Arsse::$db)->begin(),
            \Phake::verify(Arsse::$db)->userExists($old),
            \Phake::verify(Arsse::$db)->userRename($old, $new),
            \Phake::verify(Arsse::$db)->sessionDestroy($new),
            \Phake::verify(Arsse::$db)->tokenRevoke($new, "fever.login"),
            \Phake::verify($tr)->commit()
        );
    }

    public function testRenameAUserWeDoNotKnow(): void {
        $tr = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($tr);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        \Phake::when(Arsse::$db)->userAdd->thenReturn(true);
        \Phake::when(Arsse::$db)->userRename->thenReturn(true);
        \Phake::when($this->drv)->userRename->thenReturn(true);
        $u = new User($this->drv);
        $old = "john.doe@example.com";
        $new = "jane.doe@example.com";
        $this->assertTrue($u->rename($old, $new));
        \Phake::inOrder(
            \Phake::verify($this->drv)->userRename($old, $new),
            \Phake::verify(Arsse::$db)->begin(),
            \Phake::verify(Arsse::$db)->userExists($old),
            \Phake::verify(Arsse::$db)->userAdd($new, null),
            \Phake::verify($tr)->commit()
        );
    }

    public function testRenameAUserWithoutEffect(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        \Phake::when(Arsse::$db)->userAdd->thenReturn(true);
        \Phake::when(Arsse::$db)->userRename->thenReturn(true);
        \Phake::when($this->drv)->userRename->thenReturn(false);
        $u = new User($this->drv);
        $old = "john.doe@example.com";
        $this->assertFalse($u->rename($old, $old));
        \Phake::verify($this->drv)->userRename($old, $old);
    }

    public function testRenameAUserToAnInvalidName(): void {
        $u = new User($this->drv);
        $this->assertException("invalidUsername", "User", "ExceptionInput");
        $u->rename("john.doe@example.com", "john:doe@example.com");
    }

    public function testRemoveAUser(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userRemove->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
        $this->assertTrue($u->remove($user));
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify(Arsse::$db)->userRemove($user);
        \Phake::verify($this->drv)->userRemove($user);
    }

    public function testRemoveAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userRemove->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
        $this->assertTrue($u->remove($user));
        \Phake::verify(Arsse::$db)->userExists($user);
        \Phake::verify($this->drv)->userRemove($user);
    }

    public function testRemoveAMissingUser(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userRemove->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
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
        \Phake::when($this->drv)->userRemove->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
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
        \Phake::when($this->drv)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
        $this->assertSame($pass, $u->passwordSet($user, $pass));
        \Phake::verify($this->drv)->userPasswordSet($user, $pass, null);
        \Phake::verify(Arsse::$db)->userPasswordSet($user, $pass);
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
        \Phake::verify(Arsse::$db)->userPasswordSet($user, $pass);
        \Phake::verify(Arsse::$db)->sessionDestroy($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testSetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        \Phake::when($this->drv)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn($pass);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
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
        \Phake::when($this->drv)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
        $this->assertTrue($u->passwordUnset($user));
        \Phake::verify($this->drv)->userPasswordUnset($user, null);
        \Phake::verify(Arsse::$db)->userPasswordSet($user, null);
        \Phake::verify(Arsse::$db)->sessionDestroy($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testUnsetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userPasswordUnset->thenReturn(true);
        \Phake::when(Arsse::$db)->userPasswordSet->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
        $this->assertTrue($u->passwordUnset($user));
        \Phake::verify($this->drv)->userPasswordUnset($user, null);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testUnsetAPasswordForAMissingUser(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userPasswordUnset->thenThrow(new ExceptionConflict("doesNotExist"));
        $u = new User($this->drv);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->passwordUnset($user);
        } finally {
            \Phake::verify($this->drv)->userPasswordUnset($user, null);
        }
    }


    #[DataProvider('provideProperties')]
    public function testGetThePropertiesOfAUser(array $exp, array $base, array $extra): void {
        $user = "john.doe@example.com";
        $exp = array_merge(['num' => null], array_combine(array_keys(User::PROPERTIES), array_fill(0, sizeof(User::PROPERTIES), null)), $exp);
        \Phake::when($this->drv)->userPropertiesGet->thenReturn($extra);
        \Phake::when(Arsse::$db)->userPropertiesGet->thenReturn($base);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $u = new User($this->drv);
        $this->assertSame($exp, $u->propertiesGet($user));
        \Phake::verify($this->drv)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public static function provideProperties(): iterable {
        $defaults = ['num' => 1, 'admin' => false, 'lang' => null, 'tz' => "Etc/UTC"];
        return [
            [$defaults, $defaults, []],
            [$defaults, $defaults, ['num' => 2112, 'blah' => "bloo"]],
            [['num' => 1, 'admin' => true, 'lang' => "fr", 'tz' => "America/Toronto"], $defaults, ['admin' => true, 'lang' => "fr", 'tz' => "America/Toronto"]],
            [['num' => 1, 'admin' => true, 'lang' => null, 'tz' => "America/Toronto"], ['num' => 1, 'admin' => true, 'lang' => "fr", 'tz' => "America/Toronto"], ['lang' => null]],
        ];
    }

    public function testGetThePropertiesOfAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $extra = ['tz' => "Europe/Istanbul"];
        $base = ['num' => 47, 'admin' => false, 'lang' => null, 'tz' => "Etc/UTC"];
        $exp = ['num' => 47, 'admin' => false, 'lang' => null, 'tz' => "Europe/Istanbul"];
        $exp = array_merge(['num' => null], array_combine(array_keys(User::PROPERTIES), array_fill(0, sizeof(User::PROPERTIES), null)), $exp);
        \Phake::when($this->drv)->userPropertiesGet->thenReturn($extra);
        \Phake::when(Arsse::$db)->userPropertiesGet->thenReturn($base);
        \Phake::when(Arsse::$db)->userAdd->thenReturn(true);
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $u = new User($this->drv);
        $this->assertSame($exp, $u->propertiesGet($user));
        \Phake::verify($this->drv)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userPropertiesGet($user);
        \Phake::verify(Arsse::$db)->userPropertiesSet($user, $extra);
        \Phake::verify(Arsse::$db)->userAdd($user, null);
        \Phake::verify(Arsse::$db)->userExists($user);
    }

    public function testGetThePropertiesOfAMissingUser(): void {
        $user = "john.doe@example.com";
        \Phake::when($this->drv)->userPropertiesGet->thenThrow(new ExceptionConflict("doesNotExist"));
        $u = new User($this->drv);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->propertiesGet($user);
        } finally {
            \Phake::verify($this->drv)->userPropertiesGet($user);
        }
    }


    #[DataProvider('providePropertyChanges')]
    public function testSetThePropertiesOfAUser(array $in, $out): void {
        $user = "john.doe@example.com";
        if ($out instanceof \Exception) {
            $u = new User($this->drv);
            $this->assertException($out);
            $u->propertiesSet($user, $in);
        } else {
            \Phake::when(Arsse::$db)->userExists->thenReturn(true);
            \Phake::when($this->drv)->userPropertiesSet->thenReturn($out);
            \Phake::when(Arsse::$db)->userPropertiesSet->thenReturn(true);
            $u = new User($this->drv);
            $this->assertSame($out, $u->propertiesSet($user, $in));
            \Phake::verify($this->drv)->userPropertiesSet($user, $in);
            \Phake::verify(Arsse::$db)->userPropertiesSet($user, $out);
            \Phake::verify(Arsse::$db)->userExists($user);
        }
    }


    #[DataProvider('providePropertyChanges')]
    public function testSetThePropertiesOfAUserWeDoNotKnow(array $in, $out): void {
        $user = "john.doe@example.com";
        if ($out instanceof \Exception) {
            $u = new User($this->drv);
            $this->assertException($out);
            $u->propertiesSet($user, $in);
        } else {
            \Phake::when(Arsse::$db)->userExists->thenReturn(false);
            \Phake::when($this->drv)->userPropertiesSet->thenReturn($out);
            \Phake::when(Arsse::$db)->userPropertiesSet->thenReturn(true);
            $u = new User($this->drv);
            $this->assertSame($out, $u->propertiesSet($user, $in));
            \Phake::when($this->drv)->userPropertiesSet($user, $in);
            \Phake::when(Arsse::$db)->userPropertiesSet($user, $out);
            \Phake::when(Arsse::$db)->userExists($user);
            \Phake::when(Arsse::$db)->userAdd($user, null);
        }
    }

    public static function providePropertyChanges(): iterable {
        return [
            [['admin' => true],    ['admin' => true]],
            [['admin' => 2],       new ExceptionInput("invalidValue")],
            [['tz' => "Etc/UTC"],  ['tz' => "Etc/UTC"]],
            [['tz' => "Etc/blah"], new ExceptionInput("invalidTimezone")],
            [['tz' => false],      new ExceptionInput("invalidValue")],
            [['lang' => "en-ca"],  ['lang' => "en-CA"]],
            [['lang' => null],     ['lang' => null]],
        ];
    }

    public function testSetThePropertiesOfAMissingUser(): void {
        $user = "john.doe@example.com";
        $in = ['admin' => true];
        \Phake::when($this->drv)->userPropertiesSet->thenThrow(new ExceptionConflict("doesNotExist"));
        $u = new User($this->drv);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->propertiesSet($user, $in);
        } finally {
            \Phake::verify($this->drv)->userPropertiesSet($user, $in);
        }
    }
}
