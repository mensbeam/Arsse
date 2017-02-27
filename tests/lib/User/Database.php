<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test\User;
use JKingWeb\NewsSync\User\Driver;
use JKingWeb\NewsSync\User\Exception;
use JKingWeb\NewsSync\User\ExceptionAuthz;
use PasswordGenerator\Generator as PassGen;

class Database extends DriverSkeleton {

    public $db = [];

    public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
        $this->data = $data;
    }
	
    function userExists(string $user): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userExists($user);
    }

    function userAdd(string $user, string $password = null): string {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->userExists($user)) throw new Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        return parent::userAdd($user, $password);
    }

    function userRemove(string $user): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRemove($user);
    }

    function userList(string $domain = null): array {
        if($domain===null) {
            if(!$this->data->user->authorize("", __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => "global"]);
            return parent::userList();
        } else {
            $suffix = '@'.$domain;
            if(!$this->data->user->authorize($suffix, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $domain]);
            return parent::userList($domain);
        }
    }
    
    function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($newPassword===null) $newPassword = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        return parent::userPasswordSet($user, $newPassword);
    }

    function userPropertiesGet(string $user): array {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $out = parent::userPropertiesGet($user);
        unset($out['password']);
        return $out;
    }

    function userPropertiesSet(string $user, array $properties): array {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        parent::userPropertiesSet($user, $properties);
        return $this->userPropertiesGet($user);
    }

    function userRightsGet(string $user): int {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsGet($user);
    }
    
    function userRightsSet(string $user, int $level): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsSet($user, $level);
    }
}