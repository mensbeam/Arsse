<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\User;
use JKingWeb\Arsse\User\Driver;
use JKingWeb\Arsse\User\Exception;
use PasswordGenerator\Generator as PassGen;

class DriverExternalMock extends DriverSkeleton implements Driver {

    public $db = [];
    protected $data;
    protected $functions = [
        "auth"                    => Driver::FUNC_EXTERNAL,
        "userList"                => Driver::FUNC_EXTERNAL,
        "userExists"              => Driver::FUNC_EXTERNAL,
        "userAdd"                 => Driver::FUNC_EXTERNAL,
        "userRemove"              => Driver::FUNC_EXTERNAL,
        "userPasswordSet"         => Driver::FUNC_EXTERNAL,
        "userPropertiesGet"       => Driver::FUNC_EXTERNAL,
        "userPropertiesSet"       => Driver::FUNC_EXTERNAL,
        "userRightsGet"           => Driver::FUNC_EXTERNAL,
        "userRightsSet"           => Driver::FUNC_EXTERNAL,
    ];

    static public function create(\JKingWeb\Arsse\RuntimeData $data): Driver {
        return new static($data);
    }

    static public function driverName(): string {
        return "Mock External Driver";
    }

    public function driverFunctions(string $function = null) {
        if($function===null) return $this->functions;
        if(array_key_exists($function, $this->functions)) {
            return $this->functions[$function];
        } else {
            return Driver::FUNC_NOT_IMPLEMENTED;
        }
    }

    public function __construct(\JKingWeb\Arsse\RuntimeData $data) {
        $this->data = $data;
    }

    function auth(string $user, string $password): bool {
        if(!$this->userExists($user)) return false;
        if($password==="" && $this->db[$user]['password']==="") return true;
        if(password_verify($password, $this->db[$user]['password'])) return true;
        return false;
    }
	
    function userExists(string $user): bool {
        return parent::userExists($user);
    }

    function userAdd(string $user, string $password = null): string {
        if($this->userExists($user)) throw new Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        return parent::userAdd($user, $password);
    }

    function userRemove(string $user): bool {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRemove($user);
    }

    function userList(string $domain = null): array {
        if($domain===null) {
            return parent::userList();
        } else {
            return parent::userList($domain);
        }
    }
    
    function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($newPassword===null) $newPassword = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        return parent::userPasswordSet($user, $newPassword);
    }

    function userPropertiesGet(string $user): array {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userPropertiesGet($user);
    }

    function userPropertiesSet(string $user, array $properties): array {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        parent::userPropertiesSet($user, $properties);
        return $this->userPropertiesGet($user);
    }

    function userRightsGet(string $user): int {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsGet($user);
    }
    
    function userRightsSet(string $user, int $level): bool {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return parent::userRightsSet($user, $level);
    }
}