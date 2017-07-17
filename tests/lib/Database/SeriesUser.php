<?php
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
                ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', "Hard Lip Herbert", UserDriver::RIGHTS_GLOBAL_ADMIN], // password is hash of "secret"
                ["jane.doe@example.com", "", "Jane Doe", UserDriver::RIGHTS_NONE],
                ["john.doe@example.com", "", "John Doe", UserDriver::RIGHTS_NONE],
            ],
        ],
    ];

    function testCheckThatAUserExists() {
        $this->assertTrue(Arsse::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Arsse::$db->userExists("jane.doe@example.org"));
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.com", "userExists");
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.org", "userExists");
        $this->compareExpectations($this->data);
    }

    function testCheckThatAUserExistsWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userExists("jane.doe@example.com");
    }

    function testGetAPassword() {
        $hash = Arsse::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userPasswordGet");
        $this->assertTrue(password_verify("secret", $hash));
    }

    function testGetAPasswordWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordGet("admin@example.net");
    }
    
    function testAddANewUser() {
        $this->assertSame("", Arsse::$db->userAdd("john.doe@example.org", ""));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.org", "userAdd");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','name','rights']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org", null, UserDriver::RIGHTS_NONE];
        $this->compareExpectations($state);
    }

    /**
     * @depends testGetAPassword
     * @depends testAddANewUser
     */
    function testAddANewUserWithARandomPassword() {
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

    function testAddAnExistingUser() {
        $this->assertException("alreadyExists", "User");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    function testAddANewUserWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userAdd("john.doe@example.org", "");
    }
    
    function testRemoveAUser() {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userRemove");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userRemove("john.doe@example.org");
    }
    
    function testRemoveAUserWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userRemove("admin@example.net");
    }

    function testListAllUsers() {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList());
        Phake::verify(Arsse::$user)->authorize("", "userList");
    }

    function testListUsersOnADomain() {
        $users = ["jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList("example.com"));
        Phake::verify(Arsse::$user)->authorize("@example.com", "userList");
    }
    
    function testListAllUsersWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userList();
    }
    
    function testListUsersOnADomainWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userList("example.com");
    }

    /**
     * @depends testGetAPassword
     */
    function testSetAPassword() {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $pass = Arsse::$db->userPasswordSet($user, "secret");
        $hash = Arsse::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        Phake::verify(Arsse::$user)->authorize($user, "userPasswordSet");
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }

    function testSetThePasswordOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }
    
    function testSetAPasswordWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPasswordSet("john.doe@example.com", "secret");
    }

    function testGetUserProperties() {
        $exp = [
            'name'   => 'Hard Lip Herbert',
            'rights' => UserDriver::RIGHTS_GLOBAL_ADMIN,
        ];
        $props = Arsse::$db->userPropertiesGet("admin@example.net");
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userPropertiesGet");
        $this->assertArraySubset($exp, $props);
        $this->assertArrayNotHasKey("password", $props);
    }

    function testGetThePropertiesOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPropertiesGet("john.doe@example.org");
    }
    
    function testGetUserPropertiesWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPropertiesGet("john.doe@example.com");
    }

    function testSetUserProperties() {
        $try = [
            'name'     => 'James Kirk', // only this should actually change
            'password' => '000destruct0',
            'rights'   => UserDriver::RIGHTS_NONE,
            'lifeform' => 'tribble',
        ];
        $exp = [
            'name'   => 'James Kirk',
            'rights' => UserDriver::RIGHTS_GLOBAL_ADMIN,
        ];
        $props = Arsse::$db->userPropertiesSet("admin@example.net", $try);
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "userPropertiesSet");
        $this->assertArraySubset($exp, $props);
        $this->assertArrayNotHasKey("password", $props);
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','password','name','rights']]);
        $state['arsse_users']['rows'][0][2] = "James Kirk";
        $this->compareExpectations($state);
    }

    function testSetThePropertiesOfAMissingUser() {
        $try = ['name' => 'John Doe'];
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPropertiesSet("john.doe@example.org", $try);
    }
    
    function testSetUserPropertiesWithoutAuthority() {
        $try = ['name' => 'John Doe'];
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userPropertiesSet("john.doe@example.com", $try);
    }

    function testGetUserRights() {
        $user1 = "john.doe@example.com";
        $user2 = "admin@example.net";
        $this->assertSame(UserDriver::RIGHTS_NONE, Arsse::$db->userRightsGet($user1));
        $this->assertSame(UserDriver::RIGHTS_GLOBAL_ADMIN, Arsse::$db->userRightsGet($user2));
        Phake::verify(Arsse::$user)->authorize($user1, "userRightsGet");
        Phake::verify(Arsse::$user)->authorize($user2, "userRightsGet");
    }

    function testGetTheRightsOfAMissingUser() {
        $this->assertSame(UserDriver::RIGHTS_NONE, Arsse::$db->userRightsGet("john.doe@example.org"));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.org", "userRightsGet");
    }
    
    function testGetUserRightsWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userRightsGet("john.doe@example.com");
    }

    function testSetUserRights() {
        $user = "john.doe@example.com";
        $rights = UserDriver::RIGHTS_GLOBAL_ADMIN;
        $this->assertTrue(Arsse::$db->userRightsSet($user, $rights));
        Phake::verify(Arsse::$user)->authorize($user, "userRightsSet", $rights);
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','rights']]);
        $state['arsse_users']['rows'][2][1] = $rights;
        $this->compareExpectations($state);
    }

    function testSetTheRightsOfAMissingUser() {
        $rights = UserDriver::RIGHTS_GLOBAL_ADMIN;
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userRightsSet("john.doe@example.org", $rights);
    }
    
    function testSetUserRightsWithoutAuthority() {
        $rights = UserDriver::RIGHTS_GLOBAL_ADMIN;
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->userRightsSet("john.doe@example.com", $rights);
    }
}