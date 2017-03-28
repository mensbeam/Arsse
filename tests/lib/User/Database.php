<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\User;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\User\Driver;
use JKingWeb\Arsse\User\Exception;
use JKingWeb\Arsse\User\ExceptionAuthz;
use PasswordGenerator\Generator as PassGen;

class Database extends DriverSkeleton {

    public $db = [];

    public function __construct() {
    }
	
    function userExists(string $user): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userExists($user);
    }

    function userAdd(string $user, string $password = null): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->userExists($user)) throw new Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length(Data::$conf->userTempPasswordLength)->get();
        return parent::userAdd($user, $password);
    }

    function userRemove(string $user): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRemove($user);
    }

    function userList(string $domain = null): array {
        if($domain===null) {
            if(!Data::$user->authorize("", __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => "global"]);
            return parent::userList();
        } else {
            $suffix = '@'.$domain;
            if(!Data::$user->authorize($suffix, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $domain]);
            return parent::userList($domain);
        }
    }
    
    function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($newPassword===null) $newPassword = (new PassGen)->length(Data::$conf->userTempPasswordLength)->get();
        return parent::userPasswordSet($user, $newPassword);
    }

    function userPropertiesGet(string $user): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $out = parent::userPropertiesGet($user);
        unset($out['password']);
        return $out;
    }

    function userPropertiesSet(string $user, array $properties): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        parent::userPropertiesSet($user, $properties);
        return $this->userPropertiesGet($user);
    }

    function userRightsGet(string $user): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsGet($user);
    }
    
    function userRightsSet(string $user, int $level): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsSet($user, $level);
    }

    // specific to mock database

    function userPasswordGet(string $user): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return $this->db[$user]['password'];
    }
}