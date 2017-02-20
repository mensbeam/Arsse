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
}
