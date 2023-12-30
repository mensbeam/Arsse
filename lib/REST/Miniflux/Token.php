<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\ExceptionConflict;

class Token {
    protected const TOKEN_LENGTH = 32;

    public function tokenGenerate(string $user, ?string $label = null): string {
        // Miniflux produces tokens in base64url alphabet
        $t = str_replace(["+", "/"], ["-", "_"], base64_encode(random_bytes(self::TOKEN_LENGTH)));
        return Arsse::$db->tokenCreate($user, "miniflux.login", $t, null, $label);
    }

    public function tokenList(string $user): array {
        if (!Arsse::$db->userExists($user)) {
            throw new ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $out = [];
        foreach (Arsse::$db->tokenList($user, "miniflux.login") as $r) {
            $out[] = ['label' => $r['data'], 'id' => $r['id']];
        }
        return $out;
    }
}
