<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use Phake;


class TestDatabaseUser extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\Db\Tools;
    
    protected $drv;
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
                'rights'   => 'int',
            ],
            'rows' => [
                ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', "Hard Lip Herbert", User\Driver::RIGHTS_GLOBAL_ADMIN], // password is hash of "secret"
                ["jane.doe@example.com", "", "Jane Doe", User\Driver::RIGHTS_NONE],
                ["john.doe@example.com", "", "John Doe", User\Driver::RIGHTS_NONE],
            ],
        ],
    ];

    function setUp() {
        // establish a clean baseline
        $this->clearData();
        // create a default configuration
        Data::$conf = new Conf();
        // configure and create the relevant database driver
        Data::$conf->dbSQLite3File = ":memory:";
        $this->drv = new Db\SQLite3\Driver(true);
        // create the database interface with the suitable driver
        Data::$db = new Database($this->drv);
        Data::$db->schemaUpdate();
        // create a mock user manager
        Data::$user = Phake::mock(User::class);
        Phake::when(Data::$user)->authorize->thenReturn(true);
        // call the additional setup method if it exists
        if(method_exists($this, "setUpSeries")) $this->setUpSeries();
    }

	function tearDown() {
        // call the additional teardiwn method if it exists
        if(method_exists($this, "tearDownSeries")) $this->tearDownSeries();
        // clean up
		$this->drv = null;
        $this->clearData();
	}

    function setUpSeries() {
        $this->primeDatabase($this->data);
    }

    function testCheckThatAUserExists() {
        $this->assertTrue(Data::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Data::$db->userExists("jane.doe@example.org"));
        Phake::verify(Data::$user)->authorize("jane.doe@example.com", "userExists");
        Phake::verify(Data::$user)->authorize("jane.doe@example.org", "userExists");
        $this->compareExpectations($this->data);
    }

    function testCheckThatAUserExistsWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userExists("jane.doe@example.com");
    }

    function testGetAPassword() {
        $hash = Data::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        Phake::verify(Data::$user)->authorize("admin@example.net", "userPasswordGet");
        $this->assertTrue(password_verify("secret", $hash));
    }

    function testGetAPasswordWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userPasswordGet("admin@example.net");
    }
    
    function testAddANewUser() {
        $this->assertSame("", Data::$db->userAdd("john.doe@example.org", ""));
        Phake::verify(Data::$user)->authorize("john.doe@example.org", "userAdd");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','name','rights']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org", null, User\Driver::RIGHTS_NONE];
        $this->compareExpectations($state);
    }

    /**
     * @depends testGetAPassword
     * @depends testAddANewUser
     */
    function testAddANewUserWithARandomPassword() {
        $user1 = "john.doe@example.org";
        $user2 = "john.doe@example.net";
        $pass1 = Data::$db->userAdd($user1);
        $pass2 = Data::$db->userAdd($user2);
        $this->assertSame(Data::$conf->userTempPasswordLength, strlen($pass1));
        $this->assertSame(Data::$conf->userTempPasswordLength, strlen($pass2));
        $this->assertNotEquals($pass1, $pass2);
        $hash1 = Data::$db->userPasswordGet($user1);
        $hash2 = Data::$db->userPasswordGet($user2);
        Phake::verify(Data::$user)->authorize($user1, "userAdd");
        Phake::verify(Data::$user)->authorize($user2, "userAdd");
        Phake::verify(Data::$user)->authorize($user1, "userPasswordGet");
        Phake::verify(Data::$user)->authorize($user2, "userPasswordGet");
        $this->assertTrue(password_verify($pass1, $hash1), "Failed verifying password of $user1 '$pass1' against hash '$hash1'.");
        $this->assertTrue(password_verify($pass2, $hash2), "Failed verifying password of $user2 '$pass2' against hash '$hash2'.");
    }

    function testAddAnExistingUser() {
        $this->assertException("alreadyExists", "User");
        Data::$db->userAdd("john.doe@example.com", "");
    }

    function testAddANewUserWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userAdd("john.doe@example.org", "");
    }
    
    function testRemoveAUser() {
        $this->assertTrue(Data::$db->userRemove("admin@example.net"));
        Phake::verify(Data::$user)->authorize("admin@example.net", "userRemove");
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$db->userRemove("john.doe@example.org");
    }
    
    function testRemoveAUserWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userRemove("admin@example.net");
    }

    function testListAllUsers() {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Data::$db->userList());
        Phake::verify(Data::$user)->authorize("", "userList");
    }

    function testListUsersOnADomain() {
        $users = ["jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Data::$db->userList("example.com"));
        Phake::verify(Data::$user)->authorize("@example.com", "userList");
    }
    
    function testListAllUsersWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userList();
    }
    
    function testListUsersOnADomainWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userList("example.com");
    }

    /**
     * @depends testGetAPassword
     */
    function testSetAPassword() {
        $user = "john.doe@example.com";
        $this->assertEquals("", Data::$db->userPasswordGet($user));
        $pass = Data::$db->userPasswordSet($user, "secret");
        $hash = Data::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        Phake::verify(Data::$user)->authorize($user, "userPasswordSet");
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }

    function testSetThePasswordOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$db->userPasswordSet("john.doe@example.org", "secret");
    }
    
    function testSetAPasswordWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userPasswordSet("john.doe@example.com", "secret");
    }

    function testGetUserProperties() {
        $exp = [
            'name'   => 'Hard Lip Herbert',
            'rights' => User\Driver::RIGHTS_GLOBAL_ADMIN,
        ];
        $props = Data::$db->userPropertiesGet("admin@example.net");
        Phake::verify(Data::$user)->authorize("admin@example.net", "userPropertiesGet");
        $this->assertArraySubset($exp, $props);
        $this->assertArrayNotHasKey("password", $props);
    }

    function testGetThePropertiesOfAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$db->userPropertiesGet("john.doe@example.org");
    }
    
    function testGetUserPropertiesWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userPropertiesGet("john.doe@example.com");
    }

    function testSetUserProperties() {
        $try = [
            'name'     => 'James Kirk', // only this should actually change
            'password' => '000destruct0',
            'rights'   => User\Driver::RIGHTS_NONE,
            'lifeform' => 'tribble',
        ];
        $exp = [
            'name'   => 'James Kirk',
            'rights' => User\Driver::RIGHTS_GLOBAL_ADMIN,
        ];
        $props = Data::$db->userPropertiesSet("admin@example.net", $try);
        Phake::verify(Data::$user)->authorize("admin@example.net", "userPropertiesSet");
        $this->assertArraySubset($exp, $props);
        $this->assertArrayNotHasKey("password", $props);
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','password','name','rights']]);
        $state['arsse_users']['rows'][0][2] = "James Kirk";
        $this->compareExpectations($state);
    }

    function testSetThePropertiesOfAMissingUser() {
        $try = ['name' => 'John Doe'];
        $this->assertException("doesNotExist", "User");
        Data::$db->userPropertiesSet("john.doe@example.org", $try);
    }
    
    function testSetUserPropertiesWithoutAuthority() {
        $try = ['name' => 'John Doe'];
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userPropertiesSet("john.doe@example.com", $try);
    }

    function testGetUserRights() {
        $user1 = "john.doe@example.com";
        $user2 = "admin@example.net";
        $this->assertSame(User\Driver::RIGHTS_NONE, Data::$db->userRightsGet($user1));
        $this->assertSame(User\Driver::RIGHTS_GLOBAL_ADMIN, Data::$db->userRightsGet($user2));
        Phake::verify(Data::$user)->authorize($user1, "userRightsGet");
        Phake::verify(Data::$user)->authorize($user2, "userRightsGet");
    }

    function testGetTheRightsOfAMissingUser() {
        $this->assertSame(User\Driver::RIGHTS_NONE, Data::$db->userRightsGet("john.doe@example.org"));
        Phake::verify(Data::$user)->authorize("john.doe@example.org", "userRightsGet");
    }
    
    function testGetUserRightsWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userRightsGet("john.doe@example.com");
    }

    function testSetUserRights() {
        $user = "john.doe@example.com";
        $rights = User\Driver::RIGHTS_GLOBAL_ADMIN;
        $this->assertTrue(Data::$db->userRightsSet($user, $rights));
        Phake::verify(Data::$user)->authorize($user, "userRightsSet", $rights);
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id','rights']]);
        $state['arsse_users']['rows'][2][1] = $rights;
        $this->compareExpectations($state);
    }

    function testSetTheRightsOfAMissingUser() {
        $rights = User\Driver::RIGHTS_GLOBAL_ADMIN;
        $this->assertException("doesNotExist", "User");
        Data::$db->userRightsSet("john.doe@example.org", $rights);
    }
    
    function testSetUserRightsWithoutAuthority() {
        $rights = User\Driver::RIGHTS_GLOBAL_ADMIN;
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->userRightsSet("john.doe@example.com", $rights);
    }
}