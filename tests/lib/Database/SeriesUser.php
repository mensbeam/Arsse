<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Driver as UserDriver;
use Phake;

trait SeriesUser {
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
                'rights'   => 'int',
            ],
            'rows' => [
                ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', "Hard Lip Herbert", 100], // password is hash of "secret"
                ["jane.doe@example.com", "", "Jane Doe", 0],
                ["john.doe@example.com", "", "John Doe", 0],
            ],
        ],
    ];

    public function testCheckThatAUserExists() {
        $this->assertTrue(Arsse::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Arsse::$db->userExists("jane.doe@example.org"));
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.com", "userExists");
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.org", "userExists");
        $this->compareExpectations($this->data);
    }

    public function testCheckThatAUserExistsWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userExists("jane.doe@example.com");
    }

    public function testGetAPassword() {
        $hash = Arsse::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userPasswordGet");
        $this->assertTrue(password_verify("secret", $hash));
    }

    public function testGetThePasswordOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordGet("john.doe@example.org");
    }

    public function testGetAPasswordWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordGet("admin@example.net");
    }

    public function testAddANewUser() {
        $this->assertSame("", Arsse::$db->userAdd("john.doe@example.org", ""));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.org", "userAdd");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','name','rights']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org", null, 0];
        $this->compareExpectations($state);
    }

    /**
     * @depends testGetAPassword
     * @depends testAddANewUser
     */
    public function testAddANewUserWithARandomPassword() {
        $user1 = "john.doe@example.org";
        $user2 = "john.doe@example.net";
        $pass1 = Arsse::$db->userAdd($user1);
        $pass2 = Arsse::$db->userAdd($user2);
        $this->assertSame(Arsse::$conf->userTempPasswordLength, strlen($pass1));
        $this->assertSame(Arsse::$conf->userTempPasswordLength, strlen($pass2));
        $this->assertNotEquals($pass1, $pass2);
        $hash1 = Arsse::$db->userPasswordGet($user1);
        $hash2 = Arsse::$db->userPasswordGet($user2);
        Phake::verify(Arsse::$user)->authorize($user1, "userAdd");
        Phake::verify(Arsse::$user)->authorize($user2, "userAdd");
        Phake::verify(Arsse::$user)->authorize($user1, "userPasswordGet");
        Phake::verify(Arsse::$user)->authorize($user2, "userPasswordGet");
        $this->assertTrue(password_verify($pass1, $hash1), "Failed verifying password of $user1 '$pass1' against hash '$hash1'.");
        $this->assertTrue(password_verify($pass2, $hash2), "Failed verifying password of $user2 '$pass2' against hash '$hash2'.");
    }

    public function testAddAnExistingUser() {
        $this->assertException("alreadyExists", "User");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    public function testAddANewUserWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userAdd("john.doe@example.org", "");
    }

    public function testRemoveAUser() {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userRemove");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations($state);
    }

    public function testRemoveAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userRemove("john.doe@example.org");
    }

    public function testRemoveAUserWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userRemove("admin@example.net");
    }

    public function testListAllUsers() {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList());
        Phake::verify(Arsse::$user)->authorize("", "userList");
    }

    public function testListAllUsersWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userList();
    }

    /**
     * @depends testGetAPassword
     */
    public function testSetAPassword() {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $pass = Arsse::$db->userPasswordSet($user, "secret");
        $hash = Arsse::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        Phake::verify(Arsse::$user)->authorize($user, "userPasswordSet");
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }
    public function testSetARandomPassword() {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $pass = Arsse::$db->userPasswordSet($user);
        $hash = Arsse::$db->userPasswordGet($user);
    }

    public function testSetThePasswordOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }

    public function testSetAPasswordWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordSet("john.doe@example.com", "secret");
    }
}
