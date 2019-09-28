<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

use Psr\Http\Message\MessageInterface;

class HTTP {
    public static function matchType(MessageInterface $msg, string ...$type): bool {
        $header = $msg->getHeaderLine("Content-Type") ?? "";
        foreach ($type as $t) {
            $pattern = "/^".preg_quote(trim($t), "/")."($|;|,)/i";
            if (preg_match($pattern, $header)) {
                return true;
            }
        }
        return false;
    }
}
