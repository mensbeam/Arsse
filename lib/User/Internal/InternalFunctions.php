<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\User\Internal;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Exception;

trait InternalFunctions {
    protected $actor = [];

    public function __construct() {
    }

    public function auth(string $user, string $password): bool {
        try {
            $hash = Arsse::$db->userPasswordGet($user);
        } catch (Exception $e) {
            return false;
        }
        if ($password==="" && $hash==="") {
            return true;
        }
        return password_verify($password, $hash);
    }

    public function userExists(string $user): bool {
        return Arsse::$db->userExists($user);
    }

    public function userAdd(string $user, string $password = null): string {
        return Arsse::$db->userAdd($user, $password);
    }

    public function userRemove(string $user): bool {
        return Arsse::$db->userRemove($user);
    }

    public function userList(string $domain = null): array {
        return Arsse::$db->userList($domain);
    }

    public function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string {
        return Arsse::$db->userPasswordSet($user, $newPassword);
    }

    public function userPropertiesGet(string $user): array {
        return Arsse::$db->userPropertiesGet($user);
    }

    public function userPropertiesSet(string $user, array $properties): array {
        return Arsse::$db->userPropertiesSet($user, $properties);
    }

    public function userRightsGet(string $user): int {
        return Arsse::$db->userRightsGet($user);
    }

    public function userRightsSet(string $user, int $level): bool {
        return Arsse::$db->userRightsSet($user, $level);
    }
}
