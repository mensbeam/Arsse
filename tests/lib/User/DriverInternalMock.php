<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test\User;
use JKingWeb\NewsSync\User\Driver;

class DriverInternalMock extends Database implements Driver {

    public $db = [];
    protected $data;
    protected $functions = [
        "auth"                    => Driver::FUNC_INTERNAL,
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
}