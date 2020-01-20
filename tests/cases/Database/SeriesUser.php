<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesUser {
    protected function setUpSeriesUser(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                ],
                'rows' => [
                    ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW'], // password is hash of "secret"
                    ["jane.doe@example.com", ""],
                    ["john.doe@example.com", ""],
                ],
            ],
        ];
    }

    protected function tearDownSeriesUser(): void {
        unset($this->data);
    }

    public function testCheckThatAUserExists(): void {
        $this->assertTrue(Arsse::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Arsse::$db->userExists("jane.doe@example.org"));
        \Phake::verify(Arsse::$user)->authorize("jane.doe@example.com", "userExists");
        \Phake::verify(Arsse::$user)->authorize("jane.doe@example.org", "userExists");
        $this->compareExpectations(static::$drv, $this->data);
    }

    public function testCheckThatAUserExistsWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userExists("jane.doe@example.com");
    }

    public function testGetAPassword(): void {
        $hash = Arsse::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        \Phake::verify(Arsse::$user)->authorize("admin@example.net", "userPasswordGet");
        $this->assertTrue(password_verify("secret", $hash));
    }

    public function testGetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordGet("john.doe@example.org");
    }

    public function testGetAPasswordWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordGet("admin@example.net");
    }

    public function testAddANewUser(): void {
        $this->assertTrue(Arsse::$db->userAdd("john.doe@example.org", ""));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.org", "userAdd");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddAnExistingUser(): void {
        $this->assertException("alreadyExists", "User");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    public function testAddANewUserWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userAdd("john.doe@example.org", "");
    }

    public function testRemoveAUser(): void {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        \Phake::verify(Arsse::$user)->authorize("admin@example.net", "userRemove");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userRemove("john.doe@example.org");
    }

    public function testRemoveAUserWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userRemove("admin@example.net");
    }

    public function testListAllUsers(): void {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList());
        \Phake::verify(Arsse::$user)->authorize("", "userList");
    }

    public function testListAllUsersWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userList();
    }

    /**
     * @depends testGetAPassword
     */
    public function testSetAPassword(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, $pass));
        $hash = Arsse::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        \Phake::verify(Arsse::$user)->authorize($user, "userPasswordSet");
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }

    public function testUnsetAPassword(): void {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, null));
        $this->assertNull(Arsse::$db->userPasswordGet($user));
    }

    public function testSetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }

    public function testSetAPasswordWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordSet("john.doe@example.com", "secret");
    }
}
