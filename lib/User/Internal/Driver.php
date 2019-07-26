<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\User\Internal;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Exception;

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
        } catch (Exception $e) {
            return false;
        }
        if ($password==="" && $hash==="") {
            return true;
        }
        return password_verify($password, $hash);
    }

    public function authorize(string $affectedUser, string $action): bool {
        return true;
    }

    public function userExists(string $user): bool {
        return Arsse::$db->userExists($user);
    }

    public function userAdd(string $user, string $password = null) {
        if (isset($password)) {
            // only add the user if the password is not null; the user manager will retry with a generated password if null is returned
            Arsse::$db->userAdd($user, $password);
        }
        return $password;
    }

    public function userRemove(string $user): bool {
        return Arsse::$db->userRemove($user);
    }

    public function userList(): array {
        return Arsse::$db->userList();
    }

    public function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null) {
        // do nothing: the internal database is updated regardless of what the driver does (assuming it does not throw an exception)
        return $newPassword;
    }

    public function userPasswordUnset(string $user, string $oldPassword = null): bool {
        // do nothing: the internal database is updated regardless of what the driver does (assuming it does not throw an exception)
        // throw an exception if the user does not exist
        if (!$this->userExists($user)) {
            throw new Exception("doesNotExist", ['action' => "userPasswordUnset", 'user' => $user]);
        } else {
            return true;
        }
    }

    protected function userPasswordGet(string $user) {
        return Arsse::$db->userPasswordGet($user);
    }
}
