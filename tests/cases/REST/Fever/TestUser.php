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

/** @covers \JKingWeb\Arsse\REST\Fever\User<extended> */
class TestUser extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $u;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock user manager
        $this->userMock = $this->mock(User::class);
        $this->userMock->auth->returns(true);
        // create a mock database interface
        $this->dbMock = $this->mock(Database::class);
        $this->dbMock->begin->returns($this->mock(Transaction::class));
    }

    protected function prepTest(): FeverUser {
        Arsse::$user = $this->userMock->get();
        Arsse::$db = $this->dbMock->get();
        // instantiate the handler
        return new FeverUser;
    }

    /** @dataProvider providePasswordCreations */
    public function testRegisterAUserPassword(string $user, string $password = null, $exp): void {
        $this->userMock->generatePassword->returns("RANDOM_PASSWORD");
        $this->dbMock->tokenCreate->does(function($user, $class, $id = null) {
            return $id ?? "RANDOM_TOKEN";
        });
        $this->dbMock->tokenCreate->with("john.doe@example.org", $this->anything(), $this->anything())->throws(new UserException("doesNotExist"));
        try {
            if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
                $this->assertException($exp);
                $this->prepTest()->register($user, $password);
            } else {
                $this->assertSame($exp, $this->prepTest()->register($user, $password));
            }
        } finally {
            $this->dbMock->tokenRevoke->calledWith($user, "fever.login");
            $this->dbMock->tokenCreate->calledWith($user, "fever.login", md5($user.":".($password ?? "RANDOM_PASSWORD")));
        }
    }

    public function providePasswordCreations(): iterable {
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
        $this->dbMock->tokenRevoke->returns(3);
        $this->assertTrue($this->prepTest()->unregister("jane.doe@example.com"));
        $this->dbMock->tokenRevoke->calledWith("jane.doe@example.com", "fever.login");
        $this->dbMock->tokenRevoke->returns(0);
        $this->assertFalse($this->prepTest()->unregister("john.doe@example.com"));
        $this->dbMock->tokenRevoke->calledWith("john.doe@example.com", "fever.login");
    }

    /** @dataProvider provideUserAuthenticationRequests */
    public function testAuthenticateAUserName(string $user, string $password, bool $exp): void {
        $this->dbMock->tokenLookup->throws(new ExceptionInput("constraintViolation"));
        $this->dbMock->tokenLookup->with("fever.login", md5("jane.doe@example.com:secret"))->returns(['user' => "jane.doe@example.com"]);
        $this->dbMock->tokenLookup->with("fever.login", md5("john.doe@example.com:superman"))->returns(['user' => "john.doe@example.com"]);
        $this->assertSame($exp, $this->prepTest()->authenticate($user, $password));
    }

    public function provideUserAuthenticationRequests(): iterable {
        return [
            ["jane.doe@example.com", "secret",   true],
            ["jane.doe@example.com", "superman", false],
            ["john.doe@example.com", "secret",   false],
            ["john.doe@example.com", "superman", true],
        ];
    }
}
