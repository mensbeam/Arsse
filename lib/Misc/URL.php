<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

/**
 * A collection of functions for manipulating URLs
 */
class URL {
    /** User component */
    const P_USER = 1;
    /** Password component */
    const P_PASS = 2;
    /** Path segment component */
    const P_PATH = 3;
    /** Full query component  */
    const P_QUERY = 4;

    /** Normalizes an absolute URL
     * 
     * Normalizations performed are:
     * 
     * - Lowercasing scheme
     * - Lowercasing host names
     * - IDN normalization (IDN rather than punycode is returned)
     * - IPv6 address normalization
     * - Resolution of relative path segments
     * - Discarding empty path segments
     * - Discarding empty queries
     * - %-encoding normalization
     * - Fragment discarding
     * 
     * @param string $url The URL to normalize. Relative URLs are returned unchanged
     * @param string $u Username to add to the URL, replacing any existing credentials
     * @param string $p Password to add to the URL, if a username is specified
     */
    public static function normalize(string $url, string $u = null, string $p = null): string {
        extract(parse_url($url));
        if (!isset($scheme) || !isset($host) || !strlen($host)) {
            return $url;
        }
        $out = strtolower($scheme)."://";
        if (strlen($u ?? "")) {
            $out .= self::normalizePart(rawurlencode($u), self::P_USER, false);
            if (strlen($p ?? "")) {
                $out .= ":".self::normalizePart(rawurlencode($p), self::P_PASS, false);
            }
            $out .= "@";
        } elseif (strlen($user ?? "")) {
            $out .= self::normalizePart($user, self::P_USER);
            if (strlen($pass ?? "")) {
                $out .= ":".self::normalizePart($pass, self::P_PASS);
            }
            $out .= "@";
        }
        if ($host[0] === "[") {
            $out .= self::normalizeIPv6($host);
        } else {
            $out .= self::normalizeHost($host);
        }
        $out .= isset($port) ? ":$port" : "";
        $out .= self::normalizePath($path ?? "");
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

    /** Normalizes the whole path segment to remove empty segments and relative segments */
    protected static function normalizePath(string $path): string {
        $parts = explode("/", $path);
        $out = [];
        foreach($parts as $p) {
            switch ($p) {
                case "":
                case ".":
                    break;
                case "..":
                    array_pop($out);
                    break;
                default:
                    $out[] = self::normalizePart($p, self::P_PATH);
            }
        }
        return str_replace("//", "/", "/".implode("/", $out).(substr($path, -1) === "/" ? "/" : ""));
    }
}
