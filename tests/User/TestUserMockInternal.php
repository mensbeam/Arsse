<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestUserMockInternal extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\User\CommonTests;
    
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
}
