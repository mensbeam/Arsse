<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestUser extends \PHPUnit\Framework\TestCase {
    use Test\Tools;
    
	protected $data;

    function setUp() {
		$drv = Test\User\DriverInternalMock::class;
		$conf = new Conf();
		$conf->userDriver = $drv;
		$conf->userAuthPreferHTTP = true;
		$this->data = new Test\RuntimeData($conf);
		$this->data->user = new User($this->data);
		$this->data->db = new $drv($this->data);
		Test\User\DriverInternalMock::$db = [];
		$_SERVER['PHP_AUTH_USER'] = "john.doe@example.com";
		$_SERVER['PHP_AUTH_PW'] = "secret";
	}

	function testAddingAUser() {
		$this->assertCount(0,$this->data->user->list());
		$this->data->user->add($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
		$this->assertCount(1,$this->data->user->list());
		$this->assertException("alreadyExists", "User");
		$this->data->user->add($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
	}
}
