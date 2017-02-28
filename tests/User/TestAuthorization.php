<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestAuthorization extends \PHPUnit\Framework\TestCase {
    use Test\Tools;
    
	const USERS = [
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
		// invalid rights levels 
		'bad1@example.com' => User\Driver::RIGHTS_NONE+1,
		'bad1@example.org' => User\Driver::RIGHTS_NONE+1,
		'bad2@example.com' => User\Driver::RIGHTS_DOMAIN_MANAGER+1,
		'bad2@example.org' => User\Driver::RIGHTS_DOMAIN_MANAGER+1,
		'bad3@example.com' => User\Driver::RIGHTS_DOMAIN_ADMIN+1,
		'bad3@example.org' => User\Driver::RIGHTS_DOMAIN_ADMIN+1,
		'bad4@example.com' => User\Driver::RIGHTS_GLOBAL_MANAGER+1,
		'bad4@example.org' => User\Driver::RIGHTS_GLOBAL_MANAGER+1,
		'bad5@example.com' => User\Driver::RIGHTS_GLOBAL_ADMIN+1,
		'bad5@example.org' => User\Driver::RIGHTS_GLOBAL_ADMIN+1,

	];
	const LEVELS = [
		User\Driver::RIGHTS_NONE,
		User\Driver::RIGHTS_DOMAIN_MANAGER,
		User\Driver::RIGHTS_DOMAIN_ADMIN,
		User\Driver::RIGHTS_GLOBAL_MANAGER,
		User\Driver::RIGHTS_GLOBAL_ADMIN,
	];
	const DOMAINS = [
		'@example.com',
		'@example.org',
		"",
	];

	protected $data;

    function setUp(string $drv = Test\User\DriverInternalMock::class, string $db = null) {
		$conf = new Conf();
		$conf->userDriver = $drv;
		$conf->userAuthPreferHTTP = true;
		$conf->userComposeNames = true;
		$this->data = new Test\RuntimeData($conf);
		if($db !== null) {
			$this->data->db = new $db($this->data);
		}
		$this->data->user = new User($this->data);
		$this->data->user->authorizationEnabled(false);
		foreach(self::USERS as $user => $level) {
			$this->data->user->add($user, "");
			$this->data->user->rightsSet($user, $level);
		}
		$this->data->user->authorizationEnabled(true);
	}

	function testSelfActionLogic() {
		foreach(array_keys(self::USERS) as $user) {
			$this->data->user->auth($user, "");
			// users should be able to do basic actions for themselves
			$this->assertTrue($this->data->user->authorize($user, "userExists"), "User $user could not act for themselves.");
			$this->assertTrue($this->data->user->authorize($user, "userRemove"), "User $user could not act for themselves.");
		}
	}

	function testRegularUserLogic() {
		foreach(self::USERS as $actor => $rights) {
			if($rights != User\Driver::RIGHTS_NONE) continue;
			$this->data->user->auth($actor, "");
			foreach(array_keys(self::USERS) as $affected) {
				// regular users should only be able to act for themselves
				if($actor==$affected) {
					$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
					$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
					$this->assertFalse($this->data->user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// they should never be able to set rights
				foreach(self::LEVELS as $level) {
					$this->assertFalse($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
				}
			}
			// they should not be able to list users
			foreach(self::DOMAINS as $domain) {
				$this->assertFalse($this->data->user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
			}
		}
	}

	function testDomainManagerLogic() {
		foreach(self::USERS as $actor => $actorRights) {
			if($actorRights != User\Driver::RIGHTS_DOMAIN_MANAGER) continue;
			$actorDomain = substr($actor,strrpos($actor,"@")+1);
			$this->data->user->auth($actor, "");
			foreach(self::USERS as $affected => $affectedRights) {
				$affectedDomain = substr($affected,strrpos($affected,"@")+1);
				// domain managers should be able to check any user on the same domain
				if($actorDomain==$affectedDomain) {
					$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// they should only be able to act for regular users on the same domain
				if($actor==$affected || ($actorDomain==$affectedDomain && $affectedRights==User\Driver::RIGHTS_NONE)) {
					$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// and they should only be able to set their own rights to regular user
				foreach(self::LEVELS as $level) {
					if($actor==$affected && in_array($level, [User\Driver::RIGHTS_NONE, User\Driver::RIGHTS_DOMAIN_MANAGER])) {
						$this->assertTrue($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
					} else {
						$this->assertFalse($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
					}
				}
			}
			// they should also be able to list all users on their own domain
			foreach(self::DOMAINS as $domain) {
				if($domain=="@".$actorDomain) {
					$this->assertTrue($this->data->user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
				}
			}
		}
	}

	function testDomainAdministratorLogic() {
		foreach(self::USERS as $actor => $actorRights) {
			if($actorRights != User\Driver::RIGHTS_DOMAIN_ADMIN) continue;
			$actorDomain = substr($actor,strrpos($actor,"@")+1);
			$this->data->user->auth($actor, "");
			$allowed = [User\Driver::RIGHTS_NONE,User\Driver::RIGHTS_DOMAIN_MANAGER,User\Driver::RIGHTS_DOMAIN_ADMIN];
			foreach(self::USERS as $affected => $affectedRights) {
				$affectedDomain = substr($affected,strrpos($affected,"@")+1);
				// domain admins should be able to check any user on the same domain
				if($actorDomain==$affectedDomain) {
					$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// they should be able to act for any user on the same domain who is not a global manager or admin
				if($actorDomain==$affectedDomain && in_array($affectedRights, $allowed)) {
					$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// they should be able to set rights for any user on their domain who is not a global manager or admin, up to domain admin level
				foreach(self::LEVELS as $level) {
					if($actorDomain==$affectedDomain && in_array($affectedRights, $allowed) && in_array($level, $allowed)) {
						$this->assertTrue($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
					} else {
						$this->assertFalse($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
					}
				}
			}
			// they should also be able to list all users on their own domain
			foreach(self::DOMAINS as $domain) {
				if($domain=="@".$actorDomain) {
					$this->assertTrue($this->data->user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
				}
			}
		}
	}

	function testGlobalManagerLogic() {
		foreach(self::USERS as $actor => $actorRights) {
			if($actorRights != User\Driver::RIGHTS_GLOBAL_MANAGER) continue;
			$actorDomain = substr($actor,strrpos($actor,"@")+1);
			$this->data->user->auth($actor, "");
			foreach(self::USERS as $affected => $affectedRights) {
				$affectedDomain = substr($affected,strrpos($affected,"@")+1);
				// global managers should be able to check any user 
				$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
				// they should only be able to act for regular users
				if($actor==$affected || $affectedRights==User\Driver::RIGHTS_NONE) {
					$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// and they should only be able to set their own rights to regular user
				foreach(self::LEVELS as $level) {
					if($actor==$affected && in_array($level, [User\Driver::RIGHTS_NONE, User\Driver::RIGHTS_GLOBAL_MANAGER])) {
						$this->assertTrue($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
					} else {
						$this->assertFalse($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
					}
				}
			}
			// they should also be able to list all users
			foreach(self::DOMAINS as $domain) {
				$this->assertTrue($this->data->user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
			}
		}
	}

	function testGlobalAdministratorLogic() {
		foreach(self::USERS as $actor => $actorRights) {
			if($actorRights != User\Driver::RIGHTS_GLOBAL_ADMIN) continue;
			$this->data->user->auth($actor, "");
			// global admins can do anything
			foreach(self::USERS as $affected => $affectedRights) {
				$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
				$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				foreach(self::LEVELS as $level) {
					$this->assertTrue($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
				}
			}
			foreach(self::DOMAINS as $domain) {
				$this->assertTrue($this->data->user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
			}
		}
	}

	function testInvalidLevelLogic() {
		foreach(self::USERS as $actor => $rights) {
			if(in_array($rights, self::LEVELS)) continue;
			$this->data->user->auth($actor, "");
			foreach(array_keys(self::USERS) as $affected) {
				// users with unknown/invalid rights should be treated just like regular users and only be able to act for themselves
				if($actor==$affected) {
					$this->assertTrue($this->data->user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
					$this->assertTrue($this->data->user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
				} else {
					$this->assertFalse($this->data->user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
					$this->assertFalse($this->data->user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
				}
				// they should never be able to set rights
				foreach(self::LEVELS as $level) {
					$this->assertFalse($this->data->user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
				}
			}
			// they should not be able to list users
			foreach(self::DOMAINS as $domain) {
				$this->assertFalse($this->data->user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
			}
		}
	}

	function testInternalExceptionLogic() {
		$test = [
			'userExists',
			'userRemove',
			'userAdd',
			'userPasswordSet',
			'userPropertiesGet',
			'userPropertiesSet',
			'userRightsGet',
			'userRightsSet',
			'userList',
		];
		$this->data->user->auth("gadm@example.com", "");
		$this->assertCount(0, $this->checkExceptions("user@example.org"));
		$this->data->user->auth("user@example.com", "");
		$this->assertCount(sizeof($test), $this->checkExceptions("user@example.org"));
	}

	function testExternalExceptionLogic() {
		// set up the test for an external driver
		$this->setUp(Test\User\DriverExternalMock::class, Test\User\Database::class);
		// run the previous test with the external driver set up
		$this->testInternalExceptionLogic();
	}

	protected function checkExceptions(string $user): array {
		$err = [];
		try {
			$this->data->user->exists($user);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userExists";
		}
		try {
			$this->data->user->remove($user);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userRemove";
		}
		try {
			$this->data->user->add($user, "");
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userAdd";
		}
		try {
			$this->data->user->passwordSet($user, "");
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userPasswordSet";
		}
		try {
			$this->data->user->propertiesGet($user);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userPropertiesGet";
		}
		try {
			$this->data->user->propertiesSet($user, []);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userPropertiesSet";
		}
		try {
			$this->data->user->rightsGet($user);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userRightsGet";
		}
		try {
			$this->data->user->rightsSet($user, User\Driver::RIGHTS_GLOBAL_ADMIN);
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userRightsSet";
		}
		try {
			$this->data->user->list();
		} catch(User\ExceptionAuthz $e) {
			$err[] = "userList";
		}
		return $err;
	}
}