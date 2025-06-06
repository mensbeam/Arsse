<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\User\Internal;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\ExceptionConflict;

class Driver implements \JKingWeb\Arsse\User\Driver {
    public function __construct() {
    }

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.User.Internal.Name");
    }

    public function auth(string $user, string $password): bool {
        try {
            $hash = $this->userPasswordGet($user);
            if (is_null($hash)) {
                return false;
            }
        } catch (ExceptionConflict $e) {
            return false;
        }
        if ($password === "" && $hash === "") {
            return true;
        }
        return password_verify($password, $hash);
    }

    public function userAdd(string $user, ?string $password = null): ?string {
        if (isset($password)) {
            // only add the user if the password is not null; the user manager will retry with a generated password if null is returned
            Arsse::$db->userAdd($user, $password);
        }
        return $password;
    }

    public function userRename(string $user, string $newName): bool {
        // do nothing: the internal database is updated regardless of what the driver does (assuming it does not throw an exception)
        // throw an exception if the user does not exist
        if (!$this->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ['action' => __FUNCTION__, 'user' => $user]);
        } else {
            return !($user === $newName);
        }
    }

    public function userRemove(string $user): bool {
        return Arsse::$db->userRemove($user);
    }

    public function userList(): array {
        return Arsse::$db->userList();
    }

    public function userPasswordSet(string $user, ?string $newPassword, ?string $oldPassword = null): ?string {
        // do nothing: the internal database is updated regardless of what the driver does (assuming it does not throw an exception)
        // throw an exception if the user does not exist
        if (!$this->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ['action' => __FUNCTION__, 'user' => $user]);
        } else {
            return $newPassword;
        }
    }

    public function userPasswordUnset(string $user, ?string $oldPassword = null): bool {
        // do nothing: the internal database is updated regardless of what the driver does (assuming it does not throw an exception)
        // throw an exception if the user does not exist
        if (!$this->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ['action' => __FUNCTION__, 'user' => $user]);
        } else {
            return true;
        }
    }

    protected function userPasswordGet(string $user): ?string {
        return Arsse::$db->userPasswordGet($user);
    }

    protected function userExists(string $user): bool {
        return Arsse::$db->userExists($user);
    }

    public function userPropertiesGet(string $user): array {
        // do nothing: the internal database will retrieve everything for us
        if (!$this->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ['action' => __FUNCTION__, 'user' => $user]);
        } else {
            return [];
        }
    }

    public function userPropertiesSet(string $user, array $data): array {
        // do nothing: the internal database will set everything for us
        if (!$this->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ['action' => __FUNCTION__, 'user' => $user]);
        } else {
            return $data;
        }
    }
}
