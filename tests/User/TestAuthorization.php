<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestAuthorization extends \PHPUnit\Framework\TestCase {
    use Test\Tools;
    
	const USER1 = "john.doe@example.com";
	const USER2 = "jane.doe@example.org";

	protected $data;

    function setUp() {
		$drv = Test\User\DriverInternalMock::class;
		$conf = new Conf();
		$conf->userDriver = $drv;
		$conf->userAuthPreferHTTP = true;
		$this->data = new Test\RuntimeData($conf);
		$this->data->user = new User($this->data);
		$this->data->user->authorizationEnabled(false);
		$users = [
			'user@example.com' => User\Driver::RIGHTS_NONE,
			'user@example.org' => User\Driver::RIGHTS_NONE,
			'dman@example.com' => User\Driver::RIGHTS_DOMAIN_MANAGER,
			'dman@example.org' => User\Driver::RIGHTS_DOMAIN_MANAGER,
			'dadm@example.com' => User\Driver::RIGHTS_DOMAIN_ADMIN,
			'dadm@example.org' => User\Driver::RIGHTS_DOMAIN_ADMIN,
			'gman@example.com' => User\Driver::RIGHTS_GLOBAL_MANAGER,
			'gman@example.org' => User\Driver::RIGHTS_GLOBAL_MANAGER,
			'gadm@example.com' => User\Driver::RIGHTS_GLOBAL_ADMIN,
			'gadm@example.org' => User\Driver::RIGHTS_GLOBAL_ADMIN,
		];
		foreach($users as $user => $level) {
			$this->data->user->add($user, "");
			$this->data->user->rightsSet($user, $level);
		}
		$this->data->user->authorizationEnabled(true);
	}

	function testRegularUserActingOnSelf() {
		$u = "user@example.com";
		$this->data->user->auth($u, "");
		$this->data->user->remove($u);
		$this->assertFalse($this->data->user->exists($u));
	}
}