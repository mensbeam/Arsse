<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test\User;
use JKingWeb\NewsSync\Lang, JKingWeb\NewsSync\User\Driver;

final class DriverInternalMock implements Driver {

    protected $data;
    protected $db;
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
        return true;
    }

    function authorize(string $affectedUser, string $action, int $newRightsLevel = 0): bool {
        if($affectedUser==$this->data->user->id) return true;
		return false;
    }

    function userExists(string $user): bool {
        return true;
    }

    function userAdd(string $user, string $password = null): bool {
        return true;
    }

    function userRemove(string $user): bool {
        return true;
    }

    function userList(string $domain = null): array {
        return [];
    }
    
    function userPasswordSet(string $user, string $newPassword, string $oldPassword): bool {
        return true;
    }

    function userPropertiesGet(string $user): array {
        return [];
    }

    function userPropertiesSet(string $user, array $properties): array {
        return [];
    }

    function userRightsGet(string $user): int {
        return 0;
    }
    
    function userRightsSet(string $user, int $level): bool {
        return true;
    }    
}