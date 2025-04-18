<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\User\ExceptionConflict as UserException;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Fever\User as FeverUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\REST\Fever\User::class)]
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $u;
    protected $h;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        // instantiate the handler
        $this->h = new FeverUser;
    }

    #[DataProvider("providePasswordCreations")]
    public function testRegisterAUserPassword(string $user, ?string $password, $exp): void {
        \Phake::when(Arsse::$user)->generatePassword->thenReturn("RANDOM_PASSWORD");
        \Phake::when(Arsse::$db)->tokenCreate->thenReturnCallback(function($user, $class, $id = null) {
            return $id ?? "RANDOM_TOKEN";
        });
        \Phake::when(Arsse::$db)->tokenCreate("john.doe@example.org", $this->anything(), $this->anything())->thenThrow(new UserException("doesNotExist"));
        try {
            if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
                $this->assertException($exp);
                $this->h->register($user, $password);
            } else {
                $this->assertSame($exp, $this->h->register($user, $password));
            }
        } finally {
            \Phake::verify(Arsse::$db)->tokenRevoke($user, "fever.login");
            \Phake::verify(Arsse::$db)->tokenCreate($user, "fever.login", md5($user.":".($password ?? "RANDOM_PASSWORD")));
        }
    }

    public static function providePasswordCreations(): iterable {
        return [
            ["jane.doe@example.com", "secret", "secret"],
            ["jane.doe@example.com", "superman", "superman"],
            ["jane.doe@example.com", null, "RANDOM_PASSWORD"],
            ["john.doe@example.org", null, new UserException("doesNotExist")],
            ["john.doe@example.net", null, "RANDOM_PASSWORD"],
            ["john.doe@example.net", "secret", "secret"],
        ];
    }

    public function testUnregisterAUser(): void {
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(3);
        $this->assertTrue($this->h->unregister("jane.doe@example.com"));
        \Phake::verify(Arsse::$db)->tokenRevoke("jane.doe@example.com", "fever.login");
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(0);
        $this->assertFalse($this->h->unregister("john.doe@example.com"));
        \Phake::verify(Arsse::$db)->tokenRevoke("john.doe@example.com", "fever.login");
    }

    #[DataProvider("provideUserAuthenticationRequests")]
    public function testAuthenticateAUserName(string $user, string $password, bool $exp): void {
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("constraintViolation"));
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", md5("jane.doe@example.com:secret"))->thenReturn(['user' => "jane.doe@example.com"]);
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", md5("john.doe@example.com:superman"))->thenReturn(['user' => "john.doe@example.com"]);
        $this->assertSame($exp, $this->h->authenticate($user, $password));
    }

    public static function provideUserAuthenticationRequests(): iterable {
        return [
            ["jane.doe@example.com", "secret",   true],
            ["jane.doe@example.com", "superman", false],
            ["john.doe@example.com", "secret",   false],
            ["john.doe@example.com", "superman", true],
        ];
    }
}
