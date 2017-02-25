<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestUser extends \PHPUnit\Framework\TestCase {
    use Test\Tools;
    
	const USER1 = "john.doe@example.com";
	const USER2 = "jane.doe@example.com";

	protected $data;

    function setUp() {
		$drv = Test\User\DriverInternalMock::class;
		$conf = new Conf();
		$conf->userDriver = $drv;
		$conf->userAuthPreferHTTP = true;
		$this->data = new Test\RuntimeData($conf);
		$this->data->user = new User($this->data);
		$this->data->user->authorizationEnabled(false);
		$_SERVER['PHP_AUTH_USER'] = self::USER1;
		$_SERVER['PHP_AUTH_PW'] = "secret";
	}

	function testListUsers() {
		$this->assertCount(0,$this->data->user->list());
	}
	
	function testCheckIfAUserDoesNotExist() {
		$this->assertFalse($this->data->user->exists(self::USER1));
	}

	function testAddAUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertCount(1,$this->data->user->list());
	}

	function testCheckIfAUserDoesExist() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertTrue($this->data->user->exists(self::USER1));
	}

	function testAddADuplicateUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertException("alreadyExists", "User");
		$this->data->user->add(self::USER1, "secret");
	}

	function testAddMultipleUsers() {
		$this->data->user->add(self::USER1, "secret");
		$this->data->user->add(self::USER2, "secret");
		$this->assertCount(2,$this->data->user->list());
	}
	
	function testRemoveAUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertCount(1,$this->data->user->list());
		$this->data->user->remove(self::USER1);
		$this->assertCount(0,$this->data->user->list());
	}

	function testRemoveAMissingUser() {
		$this->assertException("doesNotExist", "User");
		$this->data->user->remove(self::USER1);
	}

	function testAuthenticateAUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertTrue($this->data->user->auth(self::USER1, "secret"));
		$this->assertFalse($this->data->user->auth(self::USER1, "superman"));
	}

	function testChangeAPassword() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertEquals("superman", $this->data->user->passwordSet(self::USER1, "superman"));
		$this->assertTrue($this->data->user->auth(self::USER1, "superman"));
		$this->assertFalse($this->data->user->auth(self::USER1, "secret"));
		$this->assertEquals($this->data->conf->userTempPasswordLength, strlen($this->data->user->passwordSet(self::USER1)));
	}

	function testChangeAPasswordForAMissingUser() {
		$this->assertException("doesNotExist", "User");
		$this->data->user->passwordSet(self::USER1, "superman");
	}

	function testGetThePropertiesOfAUser() {
		$this->data->user->add(self::USER1, "secret");
		$p = $this->data->user->propertiesGet(self::USER1);
		$this->assertArrayHasKey('id', $p);
		$this->assertArrayHasKey('name', $p);
		$this->assertArrayHasKey('domain', $p);
		$this->assertArrayHasKey('rights', $p);
		$this->assertArrayNotHasKey('password', $p);
		$this->assertEquals(self::USER1, $p['name']);
	}

	function testSetThePropertiesOfAUser() {
		$pSet = [
			'name' => 'John Doe',
			'id'   => 'invalid',
			'domain' => 'localhost',
			'rights' => User\Driver::RIGHTS_GLOBAL_ADMIN,
			'password' => 'superman',
		];
		$pGet = [
			'name' => 'John Doe',
			'id'   => self::USER1,
			'domain' => 'example.com',
			'rights' => User\Driver::RIGHTS_NONE,
		];
		$this->data->user->add(self::USER1, "secret");
		$this->data->user->propertiesSet(self::USER1, $pSet);
		$p = $this->data->user->propertiesGet(self::USER1);
		$this->assertArraySubset($pGet, $p);
		$this->assertArrayNotHasKey('password', $p);
		$this->assertFalse($this->data->user->auth(self::USER1, "superman"));
	}

	function testGetTheRightsOfAUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->assertEquals(User\Driver::RIGHTS_NONE, $this->data->user->rightsGet(self::USER1));
	}

	function testSetTheRightsOfAUser() {
		$this->data->user->add(self::USER1, "secret");
		$this->data->user->rightsSet(self::USER1, User\Driver::RIGHTS_GLOBAL_ADMIN);
		$this->assertEquals(User\Driver::RIGHTS_GLOBAL_ADMIN, $this->data->user->rightsGet(self::USER1));
	}
}
