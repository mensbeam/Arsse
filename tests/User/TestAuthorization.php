<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use Phake;


class TestAuthorization extends Test\AbstractTest {
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
        $this->clearData();
        $conf = new Conf();
        $conf->userDriver = $drv;
        $conf->userPreAuth = false;
        $conf->userComposeNames = true;
        Data::$conf = $conf;
        if($db !== null) {
            Data::$db = new $db();
        }
        Data::$user = Phake::PartialMock(User::class);
        Phake::when(Data::$user)->authorize->thenReturn(true);
        foreach(self::USERS as $user => $level) {
            Data::$user->add($user, "");
            Data::$user->rightsSet($user, $level);
        }
        Phake::reset(Data::$user);
    }

    function tearDown() {
        $this->clearData();
    }

    function testToggleLogic() {
        $this->assertTrue(Data::$user->authorizationEnabled());
        $this->assertTrue(Data::$user->authorizationEnabled(true));
        $this->assertFalse(Data::$user->authorizationEnabled(false));
        $this->assertFalse(Data::$user->authorizationEnabled(false));
        $this->assertFalse(Data::$user->authorizationEnabled(true));
        $this->assertTrue(Data::$user->authorizationEnabled(true));
    }
    
    function testSelfActionLogic() {
        foreach(array_keys(self::USERS) as $user) {
            Data::$user->auth($user, "");
            // users should be able to do basic actions for themselves
            $this->assertTrue(Data::$user->authorize($user, "userExists"), "User $user could not act for themselves.");
            $this->assertTrue(Data::$user->authorize($user, "userRemove"), "User $user could not act for themselves.");
        }
    }

    function testRegularUserLogic() {
        foreach(self::USERS as $actor => $rights) {
            if($rights != User\Driver::RIGHTS_NONE) continue;
            Data::$user->auth($actor, "");
            foreach(array_keys(self::USERS) as $affected) {
                // regular users should only be able to act for themselves
                if($actor==$affected) {
                    $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                    $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
                    $this->assertFalse(Data::$user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // they should never be able to set rights
                foreach(self::LEVELS as $level) {
                    $this->assertFalse(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
                }
            }
            // they should not be able to list users
            foreach(self::DOMAINS as $domain) {
                $this->assertFalse(Data::$user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
            }
        }
    }

    function testDomainManagerLogic() {
        foreach(self::USERS as $actor => $actorRights) {
            if($actorRights != User\Driver::RIGHTS_DOMAIN_MANAGER) continue;
            $actorDomain = substr($actor,strrpos($actor,"@")+1);
            Data::$user->auth($actor, "");
            foreach(self::USERS as $affected => $affectedRights) {
                $affectedDomain = substr($affected,strrpos($affected,"@")+1);
                // domain managers should be able to check any user on the same domain
                if($actorDomain==$affectedDomain) {
                    $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // they should only be able to act for regular users on the same domain
                if($actor==$affected || ($actorDomain==$affectedDomain && $affectedRights==User\Driver::RIGHTS_NONE)) {
                    $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // and they should only be able to set their own rights to regular user
                foreach(self::LEVELS as $level) {
                    if($actor==$affected && in_array($level, [User\Driver::RIGHTS_NONE, User\Driver::RIGHTS_DOMAIN_MANAGER])) {
                        $this->assertTrue(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
                    } else {
                        $this->assertFalse(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
                    }
                }
            }
            // they should also be able to list all users on their own domain
            foreach(self::DOMAINS as $domain) {
                if($domain=="@".$actorDomain) {
                    $this->assertTrue(Data::$user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
                }
            }
        }
    }

    function testDomainAdministratorLogic() {
        foreach(self::USERS as $actor => $actorRights) {
            if($actorRights != User\Driver::RIGHTS_DOMAIN_ADMIN) continue;
            $actorDomain = substr($actor,strrpos($actor,"@")+1);
            Data::$user->auth($actor, "");
            $allowed = [User\Driver::RIGHTS_NONE,User\Driver::RIGHTS_DOMAIN_MANAGER,User\Driver::RIGHTS_DOMAIN_ADMIN];
            foreach(self::USERS as $affected => $affectedRights) {
                $affectedDomain = substr($affected,strrpos($affected,"@")+1);
                // domain admins should be able to check any user on the same domain
                if($actorDomain==$affectedDomain) {
                    $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // they should be able to act for any user on the same domain who is not a global manager or admin
                if($actorDomain==$affectedDomain && in_array($affectedRights, $allowed)) {
                    $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // they should be able to set rights for any user on their domain who is not a global manager or admin, up to domain admin level
                foreach(self::LEVELS as $level) {
                    if($actorDomain==$affectedDomain && in_array($affectedRights, $allowed) && in_array($level, $allowed)) {
                        $this->assertTrue(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
                    } else {
                        $this->assertFalse(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
                    }
                }
            }
            // they should also be able to list all users on their own domain
            foreach(self::DOMAINS as $domain) {
                if($domain=="@".$actorDomain) {
                    $this->assertTrue(Data::$user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
                }
            }
        }
    }

    function testGlobalManagerLogic() {
        foreach(self::USERS as $actor => $actorRights) {
            if($actorRights != User\Driver::RIGHTS_GLOBAL_MANAGER) continue;
            $actorDomain = substr($actor,strrpos($actor,"@")+1);
            Data::$user->auth($actor, "");
            foreach(self::USERS as $affected => $affectedRights) {
                $affectedDomain = substr($affected,strrpos($affected,"@")+1);
                // global managers should be able to check any user
                $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                // they should only be able to act for regular users
                if($actor==$affected || $affectedRights==User\Driver::RIGHTS_NONE) {
                    $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // and they should only be able to set their own rights to regular user
                foreach(self::LEVELS as $level) {
                    if($actor==$affected && in_array($level, [User\Driver::RIGHTS_NONE, User\Driver::RIGHTS_GLOBAL_MANAGER])) {
                        $this->assertTrue(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
                    } else {
                        $this->assertFalse(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
                    }
                }
            }
            // they should also be able to list all users
            foreach(self::DOMAINS as $domain) {
                $this->assertTrue(Data::$user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
            }
        }
    }

    function testGlobalAdministratorLogic() {
        foreach(self::USERS as $actor => $actorRights) {
            if($actorRights != User\Driver::RIGHTS_GLOBAL_ADMIN) continue;
            Data::$user->auth($actor, "");
            // global admins can do anything
            foreach(self::USERS as $affected => $affectedRights) {
                $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                foreach(self::LEVELS as $level) {
                    $this->assertTrue(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted properly for $affected settings rights level $level, but the action was denied.");
                }
            }
            foreach(self::DOMAINS as $domain) {
                $this->assertTrue(Data::$user->authorize($domain, "userList"), "User $actor properly checked user list for domain '$domain', but the action was denied.");
            }
        }
    }

    function testInvalidLevelLogic() {
        foreach(self::USERS as $actor => $rights) {
            if(in_array($rights, self::LEVELS)) continue;
            Data::$user->auth($actor, "");
            foreach(array_keys(self::USERS) as $affected) {
                // users with unknown/invalid rights should be treated just like regular users and only be able to act for themselves
                if($actor==$affected) {
                    $this->assertTrue(Data::$user->authorize($affected, "userExists"), "User $actor acted properly for $affected, but the action was denied.");
                    $this->assertTrue(Data::$user->authorize($affected, "userRemove"), "User $actor acted properly for $affected, but the action was denied.");
                } else {
                    $this->assertFalse(Data::$user->authorize($affected, "userExists"), "User $actor acted improperly for $affected, but the action was allowed.");
                    $this->assertFalse(Data::$user->authorize($affected, "userRemove"), "User $actor acted improperly for $affected, but the action was allowed.");
                }
                // they should never be able to set rights
                foreach(self::LEVELS as $level) {
                    $this->assertFalse(Data::$user->authorize($affected, "userRightsSet", $level), "User $actor acted improperly for $affected settings rights level $level, but the action was allowed.");
                }
            }
            // they should not be able to list users
            foreach(self::DOMAINS as $domain) {
                $this->assertFalse(Data::$user->authorize($domain, "userList"), "User $actor improperly checked user list for domain '$domain', but the action was allowed.");
            }
        }
    }

    function testInternalExceptionLogic() {
        $tests = [
            // methods of User class to test, with parameters besides affected user
            'exists'        => [],
            'remove'        => [],
            'add'           => [''],
            'passwordSet'   => [''],
            'propertiesGet' => [],
            'propertiesSet' => [[]],
            'rightsGet'     => [],
            'rightsSet'     => [User\Driver::RIGHTS_GLOBAL_ADMIN],
            'list'          => [],
        ];
        // try first with a global admin (there should be no exception)
        Data::$user->auth("gadm@example.com", "");
        $this->assertCount(0, $this->checkExceptions("user@example.org", $tests));
        // next try with a regular user acting on another user (everything should fail)
        Data::$user->auth("user@example.com", "");
        $this->assertCount(sizeof($tests), $this->checkExceptions("user@example.org", $tests));
    }

    function testExternalExceptionLogic() {
        // set up the test for an external driver
        $this->setUp(Test\User\DriverExternalMock::class, Test\User\Database::class);
        // run the previous test with the external driver set up
        $this->testInternalExceptionLogic();
    }

    // meat of testInternalExceptionLogic and testExternalExceptionLogic
    // calls each requested function with supplied arguments, catches authorization exceptions, and returns an array of caught failed calls
    protected function checkExceptions(string $user, $tests): array {
        $err = [];
        foreach($tests as $func => $args) {
            // list method does not take an affected user, so do not unshift for that one
            if($func != "list") array_unshift($args, $user);
            try {
                call_user_func_array(array(Data::$user, $func), $args);
            } catch(User\ExceptionAuthz $e) {
                $err[] = $func;
            }
        }
        return $err;
    }

    function testMissingUserLogic() {
        Data::$user->auth("gadm@example.com", "");
        $this->assertTrue(Data::$user->authorize("user@example.com", "someFunction"));
        $this->assertException("doesNotExist", "User");
        Data::$user->authorize("this_user_does_not_exist@example.org", "someFunction");
    }
}