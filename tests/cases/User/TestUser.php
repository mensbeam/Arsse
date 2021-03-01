<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use Eloquent\Phony\Phpunit\Phony;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\ExceptionInput;
use JKingWeb\Arsse\User\Driver;

/** @covers \JKingWeb\Arsse\User */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock database interface
        $this->dbMock= $this->mock(Database::class);
        $this->dbMock->begin->returns($this->mock(\JKingWeb\Arsse\Db\Transaction::class));
        // create a mock user driver
        $this->drv = $this->mock(Driver::class);
    }
    
    protected function prepTest(?\Closure $partialMockDef = null): User {
        Arsse::$db = $this->dbMock->get();
        if ($partialMockDef) {
            $this->userMock = $this->partialMock(User::class, $this->drv->get());
            $partialMockDef($this->userMock);
            return $this->userMock->get();
        } else {
            return new User($this->drv->get());
        }
    }

    public function testConstruct(): void {
        $this->assertInstanceOf(User::class, new User($this->drv->get()));
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
        $u = $this->prepTest();
        $this->assertInstanceOf(Transaction::class, $u->begin());
        $this->dbMock->begin->calledWith();
    }

    public function testGeneratePasswords(): void {
        $u = $this->prepTest();
        $pass1 = $u->generatePassword();
        $pass2 = $u->generatePassword();
        $this->assertNotEquals($pass1, $pass2);
    }

    /** @dataProvider provideAuthentication */
    public function testAuthenticateAUser(bool $preAuth, string $user, string $password, bool $exp): void {
        Arsse::$conf->userPreAuth = $preAuth;
        $this->drv->auth->returns(false);
        $this->drv->auth->with("john.doe@example.com", "secret")->returns(true);
        $this->drv->auth->with("jane.doe@example.com", "superman")->returns(true);
        $this->dbMock->userExists->with("john.doe@example.com")->returns(true);
        $this->dbMock->userExists->with("jane.doe@example.com")->returns(false);
        $this->dbMock->userAdd->returns("");
        $u = $this->prepTest();
        $this->assertSame($exp, $u->auth($user, $password));
        $this->assertNull($u->id);
        $this->drv->auth->times((int) !$preAuth)->called();
        $this->dbMock->userExists->times($exp ? 1 : 0)->calledWith($user);
        $this->dbMock->userAdd->times($exp && $user === "jane.doe@example.com" ? 1 : 0)->calledWith($user, $password);
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
        $this->drv->userList->returns(["john.doe@example.com", "jane.doe@example.com"]);
        $u = $this->prepTest();
        $this->assertSame($exp, $u->list());
        $this->drv->userList->calledWith();
    }

    public function testLookUpAUserByNumber(): void {
        $exp = "john.doe@example.com";
        $this->dbMock->userLookup->returns($exp);
        $u = $this->prepTest();
        $this->assertSame($exp, $u->lookup(2112));
        $this->dbMock->userLookup->calledWith(2112);
    }

    public function testAddAUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userAdd->returns($pass);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertSame($pass, $u->add($user, $pass));
        $this->drv->userAdd->calledWith($user, $pass);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testAddAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userAdd->returns($pass);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertSame($pass, $u->add($user, $pass));
        $this->drv->userAdd->calledWith($user, $pass);
        $this->dbMock->userExists->calledWith($user);
        $this->dbMock->userAdd->calledWith($user, $pass);
    }

    public function testAddADuplicateUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userAdd->throws(new ExceptionConflict("alreadyExists"));
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            $this->dbMock->userExists->calledWith($user);
            $this->drv->userAdd->calledWith($user, $pass);
        }
    }

    public function testAddADuplicateUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userAdd->throws(new ExceptionConflict("alreadyExists"));
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        try {
            $u->add($user, $pass);
        } finally {
            $this->dbMock->userExists->calledWith($user);
            $this->dbMock->userAdd->calledWith($user, null);
            $this->drv->userAdd->calledWith($user, $pass);
        }
    }

    /** @dataProvider provideInvalidUserNames */
    public function testAddAnInvalidUser(string $user): void {
        $u = $this->prepTest();
        $this->assertException("invalidUsername", "User", "ExceptionInput");
        $u->add($user, "secret");
    }

    public function provideInvalidUserNames(): iterable {
        // output names with control characters
        foreach (array_merge(range(0x00, 0x1F), [0x7F]) as $ord) {
            yield [chr($ord)];
            yield ["john".chr($ord)."doe@example.com"];
        }
        // also handle colons
        yield [":"];
        yield ["john:doe@example.com"];
    }

    public function testAddAUserWithARandomPassword(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $this->drv->userAdd->returns(null)->returns($pass);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest(function ($u) use ($pass) {
            $u->generatePassword->returns($pass);
        });
        $this->assertSame($pass, $u->add($user));
        $this->drv->userAdd->calledWith($user, null);
        $this->drv->userAdd->calledWith($user, $pass);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testRenameAUser(): void {
        $tr = $this->mock(Transaction::class);
        $this->dbMock->begin->returns($tr);
        $this->dbMock->userExists->returns(true);
        $this->dbMock->userAdd->returns(true);
        $this->dbMock->userRename->returns(true);
        $this->drv->userRename->returns(true);
        $u = $this->prepTest();
        $old = "john.doe@example.com";
        $new = "jane.doe@example.com";
        $this->assertTrue($u->rename($old, $new));
        Phony::inOrder(
            $this->drv->userRename->calledWith($old, $new),
            $this->dbMock->begin->calledWith(),
            $this->dbMock->userExists->calledWith($old),
            $this->dbMock->userRename->calledWith($old, $new),
            $this->dbMock->sessionDestroy->calledWith($new),
            $this->dbMock->tokenRevoke->calledWith($new, "fever.login"),
            $tr->commit->called()
        );
    }

    public function testRenameAUserWeDoNotKnow(): void {
        $tr = $this->mock(Transaction::class);
        $this->dbMock->begin->returns($tr);
        $this->dbMock->userExists->returns(false);
        $this->dbMock->userAdd->returns(true);
        $this->dbMock->userRename->returns(true);
        $this->drv->userRename->returns(true);
        $u = $this->prepTest();
        $old = "john.doe@example.com";
        $new = "jane.doe@example.com";
        $this->assertTrue($u->rename($old, $new));
        Phony::inOrder(
            $this->drv->userRename->calledWith($old, $new),
            $this->dbMock->begin->calledWith(),
            $this->dbMock->userExists->calledWith($old),
            $this->dbMock->userAdd->calledWith($new, null),
            $tr->commit->called()
        );
    }

    public function testRenameAUserWithoutEffect(): void {
        $this->dbMock->userExists->returns(false);
        $this->dbMock->userAdd->returns(true);
        $this->dbMock->userRename->returns(true);
        $this->drv->userRename->returns(false);
        $u = $this->prepTest();
        $old = "john.doe@example.com";
        $this->assertFalse($u->rename($old, $old));
        $this->drv->userRename->calledWith($old, $old);
    }

    /** @dataProvider provideInvalidUserNames */
    public function testRenameAUserToAnInvalidName(string $new): void {
        $u = $this->prepTest();
        $this->assertException("invalidUsername", "User", "ExceptionInput");
        $u->rename("john.doe@example.com", $new);
    }

    public function testRemoveAUser(): void {
        $user = "john.doe@example.com";
        $this->drv->userRemove->returns(true);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertTrue($u->remove($user));
        $this->dbMock->userExists->calledWith($user);
        $this->dbMock->userRemove->calledWith($user);
        $this->drv->userRemove->calledWith($user);
    }

    public function testRemoveAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $this->drv->userRemove->returns(true);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertTrue($u->remove($user));
        $this->dbMock->userExists->calledWith($user);
        $this->drv->userRemove->calledWith($user);
    }

    public function testRemoveAMissingUser(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userRemove->throws(new ExceptionConflict("doesNotExist"));
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->remove($user);
        } finally {
            $this->dbMock->userExists->calledWith($user);
            $this->dbMock->userRemove->calledWith($user);
            $this->drv->userRemove->calledWith($user);
        }
    }

    public function testRemoveAMissingUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userRemove->throws(new ExceptionConflict("doesNotExist"));
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->remove($user);
        } finally {
            $this->dbMock->userExists->calledWith($user);
            $this->drv->userRemove->calledWith($user);
        }
    }

    public function testSetAPassword(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userPasswordSet->returns($pass);
        $this->dbMock->userPasswordSet->returns($pass);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertSame($pass, $u->passwordSet($user, $pass));
        $this->drv->userPasswordSet->calledWith($user, $pass, null);
        $this->dbMock->userPasswordSet->calledWith($user, $pass);
        $this->dbMock->sessionDestroy->calledWith($user);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testSetARandomPassword(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $this->drv->userPasswordSet->returns(null)->returns($pass);
        $this->dbMock->userPasswordSet->returns($pass);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest(function ($u) use ($pass) {
            $u->generatePassword->returns($pass);
        });
        $this->assertSame($pass, $u->passwordSet($user, null));
        $this->drv->userPasswordSet->calledWith($user, null, null);
        $this->drv->userPasswordSet->calledWith($user, $pass, null);
        $this->dbMock->userPasswordSet->calledWith($user, $pass);
        $this->dbMock->sessionDestroy->calledWith($user);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testSetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->drv->userPasswordSet->returns($pass);
        $this->dbMock->userPasswordSet->returns($pass);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertSame($pass, $u->passwordSet($user, $pass));
        $this->drv->userPasswordSet->calledWith($user, $pass, null);
        $this->dbMock->userAdd->calledWith($user, $pass);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testSetARandomPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $this->drv->userPasswordSet->returns(null)->returns($pass);
        $this->dbMock->userPasswordSet->returns($pass);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest(function ($u) use ($pass) {
            $u->generatePassword->returns($pass);
        });
        $this->assertSame($pass, $u->passwordSet($user, null));
        $this->drv->userPasswordSet->calledWith($user, null, null);
        $this->drv->userPasswordSet->calledWith($user, $pass, null);
        $this->dbMock->userAdd->calledWith($user, $pass);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testSetARandomPasswordForAMissingUser(): void {
        $user = "john.doe@example.com";
        $pass = "random password";
        $this->drv->userPasswordSet->throws(new ExceptionConflict("doesNotExist"));
        $u = $this->prepTest(function ($u) use ($pass) {
            $u->generatePassword->returns($pass);
        });
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->passwordSet($user, null);
        } finally {
            $this->drv->userPasswordSet->calledWith($user, null, null);
        }
    }

    public function testUnsetAPassword(): void {
        $user = "john.doe@example.com";
        $this->drv->userPasswordUnset->returns(true);
        $this->dbMock->userPasswordSet->returns(true);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertTrue($u->passwordUnset($user));
        $this->drv->userPasswordUnset->calledWith($user, null);
        $this->dbMock->userPasswordSet->calledWith($user, null);
        $this->dbMock->sessionDestroy->calledWith($user);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testUnsetAPasswordForAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $this->drv->userPasswordUnset->returns(true);
        $this->dbMock->userPasswordSet->returns(true);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertTrue($u->passwordUnset($user));
        $this->drv->userPasswordUnset->calledWith($user, null);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testUnsetAPasswordForAMissingUser(): void {
        $user = "john.doe@example.com";
        $this->drv->userPasswordUnset->throws(new ExceptionConflict("doesNotExist"));
        $u = $this->prepTest();
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->passwordUnset($user);
        } finally {
            $this->drv->userPasswordUnset->calledWith($user, null);
        }
    }

    /** @dataProvider provideProperties */
    public function testGetThePropertiesOfAUser(array $exp, array $base, array $extra): void {
        $user = "john.doe@example.com";
        $exp = array_merge(['num' => null], array_combine(array_keys(User::PROPERTIES), array_fill(0, sizeof(User::PROPERTIES), null)), $exp);
        $this->drv->userPropertiesGet->returns($extra);
        $this->dbMock->userPropertiesGet->returns($base);
        $this->dbMock->userExists->returns(true);
        $u = $this->prepTest();
        $this->assertSame($exp, $u->propertiesGet($user));
        $this->drv->userPropertiesGet->calledWith($user, true);
        $this->dbMock->userPropertiesGet->calledWith($user, true);
        $this->dbMock->userExists->calledWith($user);
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

    public function testGetThePropertiesOfAUserWeDoNotKnow(): void {
        $user = "john.doe@example.com";
        $extra = ['tz' => "Europe/Istanbul"];
        $base = ['num' => 47, 'admin' => false, 'lang' => null, 'tz' => "Etc/UTC", 'sort_asc' => false];
        $exp = ['num' => 47, 'admin' => false, 'lang' => null, 'tz' => "Europe/Istanbul", 'sort_asc' => false];
        $exp = array_merge(['num' => null], array_combine(array_keys(User::PROPERTIES), array_fill(0, sizeof(User::PROPERTIES), null)), $exp);
        $this->drv->userPropertiesGet->returns($extra);
        $this->dbMock->userPropertiesGet->returns($base);
        $this->dbMock->userAdd->returns(true);
        $this->dbMock->userExists->returns(false);
        $u = $this->prepTest();
        $this->assertSame($exp, $u->propertiesGet($user));
        $this->drv->userPropertiesGet->calledWith($user, true);
        $this->dbMock->userPropertiesGet->calledWith($user, true);
        $this->dbMock->userPropertiesSet->calledWith($user, $extra);
        $this->dbMock->userAdd->calledWith($user, null);
        $this->dbMock->userExists->calledWith($user);
    }

    public function testGetThePropertiesOfAMissingUser(): void {
        $user = "john.doe@example.com";
        $this->drv->userPropertiesGet->throws(new ExceptionConflict("doesNotExist"));
        $u = $this->prepTest();
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->propertiesGet($user);
        } finally {
            $this->drv->userPropertiesGet->calledWith($user, true);
        }
    }

    /** @dataProvider providePropertyChanges */
    public function testSetThePropertiesOfAUser(array $in, $out): void {
        $user = "john.doe@example.com";
        if ($out instanceof \Exception) {
            $u = $this->prepTest();
            $this->assertException($out);
            $u->propertiesSet($user, $in);
        } else {
            $this->dbMock->userExists->returns(true);
            $this->drv->userPropertiesSet->returns($out);
            $this->dbMock->userPropertiesSet->returns(true);
            $u = $this->prepTest();
            $this->assertSame($out, $u->propertiesSet($user, $in));
            $this->drv->userPropertiesSet->calledWith($user, $in);
            $this->dbMock->userPropertiesSet->calledWith($user, $out);
            $this->dbMock->userExists->calledWith($user);
        }
    }

    /** @dataProvider providePropertyChanges */
    public function testSetThePropertiesOfAUserWeDoNotKnow(array $in, $out): void {
        $user = "john.doe@example.com";
        if ($out instanceof \Exception) {
            $u = $this->prepTest();
            $this->assertException($out);
            $u->propertiesSet($user, $in);
        } else {
            $this->dbMock->userExists->returns(false);
            $this->drv->userPropertiesSet->returns($out);
            $this->dbMock->userPropertiesSet->returns(true);
            $u = $this->prepTest();
            $this->assertSame($out, $u->propertiesSet($user, $in));
            $this->drv->userPropertiesSet->calledWith($user, $in);
            $this->dbMock->userPropertiesSet->calledWith($user, $out);
            $this->dbMock->userExists->calledWith($user);
            $this->dbMock->userAdd->calledWith($user, null);
        }
    }

    public function providePropertyChanges(): iterable {
        return [
            [['admin' => true],    ['admin' => true]],
            [['admin' => 2],       new ExceptionInput("invalidValue")],
            [['sort_asc' => 2],    new ExceptionInput("invalidValue")],
            [['tz' => "Etc/UTC"],  ['tz' => "Etc/UTC"]],
            [['tz' => "Etc/blah"], new ExceptionInput("invalidTimezone")],
            [['tz' => false],      new ExceptionInput("invalidValue")],
            [['lang' => "en-ca"],  ['lang' => "en-CA"]],
            [['lang' => null],     ['lang' => null]],
            [['page_size' => 0],   new ExceptionInput("invalidNonZeroInteger")],
        ];
    }

    public function testSetThePropertiesOfAMissingUser(): void {
        $user = "john.doe@example.com";
        $in = ['admin' => true];
        $this->drv->userPropertiesSet->throws(new ExceptionConflict("doesNotExist"));
        $u = $this->prepTest();
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        try {
            $u->propertiesSet($user, $in);
        } finally {
            $this->drv->userPropertiesSet->calledWith($user, $in);
        }
    }
}
