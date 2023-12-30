<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\ExceptionInput;

class User {
    public function register(string $user, string $password = null): string {
        $password = $password ?? Arsse::$user->generatePassword();
        $hash = md5("$user:$password");
        $tr = Arsse::$db->begin();
        Arsse::$db->tokenRevoke($user, "fever.login");
        Arsse::$db->tokenCreate($user, "fever.login", $hash);
        $tr->commit();
        return $password;
    }

    public function unregister(string $user): bool {
        return (bool) Arsse::$db->tokenRevoke($user, "fever.login");
    }

    public function authenticate(string $user, string $password): bool {
        try {
            return (bool) Arsse::$db->tokenLookup("fever.login", md5("$user:$password"));
        } catch (ExceptionInput $e) {
            return false;
        }
    }
}
