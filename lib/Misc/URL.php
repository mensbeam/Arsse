<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class URL {
    public static function normalize(string $url, string $u = null, string $p = null): string {
        extract(parse_url($url));
        if (!isset($scheme) || !isset($host) || !strlen($host)) {
            return $url;
        }
        $out = strtolower($scheme)."://";
        if (strlen($u ?? "")) {
            $out .= self::normalizePart($u, self::P_USER, false);
            if (strlen($p ?? "")) {
                $out .= ":".self::normalizePart($p, self::P_PASS, false);
            }
            $out .= "@";
        } elseif (strlen($username ?? "")) {
            $out .= self::normalizePart($username, self::P_USER);
            if (strlen($password ?? "")) {
                $out .= ":".self::normalizePart($username, self::P_PASS);
            }
            $out .= "@";
        }
        if ($host[0] === "[") {
            $out .= self::normalizeIPv6($host);
        } else {
            $out .= self::normalizeHost($host);
        }
        if (isset($path)) {
            $out .= self::normalizePath($path);
        } else {
            $out .= "/";
        }
        if (isset($query) && strlen($query)) {
            $out .= "?".self::normalizePart($query, self::P_QUERY);
        }
        return $out;
    }

    protected static function normalizePart(string $part, int $type, bool $passthrough_encoded = true): string {
        // stub
        return $part;
    }

    protected static function normalizeHost(string $host): string {
        // stub
        return $host;
    }

    protected static function normalizeIPv6(string $addr): string {
        // stub
        return $addr;
    }

    protected static function normalizePath(string $path): string {
        // stub
        return $path;
    }


}
