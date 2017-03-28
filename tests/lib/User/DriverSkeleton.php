<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\User;
use JKingWeb\Arsse\Lang;
use JKingWeb\Arsse\User\Driver;
use JKingWeb\Arsse\User\Exception;
use JKingWeb\Arsse\User\ExceptionAuthz;
use PasswordGenerator\Generator as PassGen;

abstract class DriverSkeleton {

	protected $db = [];
    protected $data;
	
	function userExists(string $user): bool {
        return array_key_exists($user, $this->db);
    }

    function userAdd(string $user, string $password = null): string {
        $u = [
            'password' => $password ? password_hash($password, \PASSWORD_DEFAULT) : "",
            'rights'   => Driver::RIGHTS_NONE,
        ];
        $this->db[$user] = $u;
        return $password;
    }

    function userRemove(string $user): bool {
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
        $this->db[$user]['password'] = password_hash($newPassword, \PASSWORD_DEFAULT);
        return $newPassword;
    }

    function userPropertiesGet(string $user): array {
        $out = $this->db[$user];
        return $out;
    }

    function userPropertiesSet(string $user, array $properties): array {
        $this->db[$user] = array_merge($this->db[$user], $properties);
        return $this->userPropertiesGet($user);
    }

    function userRightsGet(string $user): int {
        return $this->db[$user]['rights'];
    }
    
    function userRightsSet(string $user, int $level): bool {
        $this->db[$user]['rights'] = $level;
        return true;
    }
}