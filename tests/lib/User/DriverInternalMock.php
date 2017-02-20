<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test\User;
use JKingWeb\NewsSync\Lang, JKingWeb\NewsSync\User\Driver, JKingWeb\NewsSync\User\Exception, PasswordGenerator\Generator as PassGen;

final class DriverInternalMock implements Driver {

    protected $db = [];
    protected $data;
    protected $functions = [
        "auth"                    => Driver::FUNC_INTERNAL,
        "authorize"               => Driver::FUNC_INTERNAL,
        "userList"                => Driver::FUNC_INTERNAL,
        "userExists"              => Driver::FUNC_INTERNAL,
        "userAdd"                 => Driver::FUNC_INTERNAL,
        "userRemove"              => Driver::FUNC_INTERNAL,
        "userPasswordSet"         => Driver::FUNC_INTERNAL,
        "userPropertiesGet"       => Driver::FUNC_INTERNAL,
        "userPropertiesSet"       => Driver::FUNC_INTERNAL,
        "userRightsGet"           => Driver::FUNC_INTERNAL,
        "userRightsSet"           => Driver::FUNC_INTERNAL,
    ];

    static public function create(\JKingWeb\NewsSync\RuntimeData $data): Driver {
        return new static($data);
    }

    static public function driverName(): string {
        return "Mock Internal Driver";
    }

    public function driverFunctions(string $function = null) {
        if($function===null) return $this->functions;
        if(array_key_exists($function, $this->functions)) {
            return $this->functions[$function];
        } else {
            return Driver::FUNC_NOT_IMPLEMENTED;
        }
    }

    public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
        $this->data = $data;
    }

    function auth(string $user, string $password): bool {
        if(!$this->userExists($user)) return false;
        if(password_verify($password, $this->db[$user]['password'])) return true;
        return false;
    }

    function authorize(string $affectedUser, string $action, int $newRightsLevel = 0): bool {
        return true;
    }

    function userExists(string $user): bool {
        return array_key_exists($user, $this->db);
    }

    function userAdd(string $user, string $password = null): string {
        if($this->userExists($user)) throw new Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        $u = [
            'password' => $password ? password_hash($password, \PASSWORD_DEFAULT) : null,
            'rights'   => Driver::RIGHTS_NONE,
        ];
        $this->db[$user] = $u;
        return $password;
    }

    function userRemove(string $user): bool {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        unset($this->db[$user]);
        return true;
    }

    function userList(string $domain = null): array {
        $list = array_keys($this->db);
        if($domain===null) {
            return $list;
        } else {
            $suffix = '@'.$domain;
            $len = -1 * strlen($suffix);
            return array_filter($list, function($user) use($suffix, $len) {
                return substr_compare($user, $suffix, $len);
            });
        }
    }
    
    function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($newPassword===null) $newPassword = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        $this->db[$user]['password'] = password_hash($newPassword, \PASSWORD_DEFAULT);
        return $newPassword;
    }

    function userPropertiesGet(string $user): array {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return $this->db[$user];
    }

    function userPropertiesSet(string $user, array $properties): array {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $this->db[$user] = array_merge($this->db[$user], $properties);
        return $this->userPropertiesGet($user);
    }

    function userRightsGet(string $user): int {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return $this->db[$user]['rights'];
    }
    
    function userRightsSet(string $user, int $level): bool {
        if(!$this->userExists($user)) throw new Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $this->db[$user]['rights'] = $level;
        return true;
    }    
}