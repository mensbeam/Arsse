<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Misc;

use MensBeam\Uri\Rfc3986\Parser as RfcParser;
use Uri\WhatWg\Url as WebUrl;

/**
 * A collection of functions for manipulating URLs
 */
class URL {
    /** Returns whether a URL is absolute i.e. whether it has a scheme */
    public static function absolute(string $url): bool {
        return (bool) strlen((string) parse_url($url, \PHP_URL_SCHEME));
    }

    /** Normalizes a URL
     *
     * Normalizations performed are:
     *
     * - Lowercasing scheme
     * - Lowercasing ASCII host names
     * - IDN normalization
     * - IPv6 address normalization
     * - Resolution of relative path segments
     * - Discarding empty queries
     * - Generic percent-encoding normalization
     * - Fragment discarding
     *
     * It does NOT drop trailing slashes from paths, nor does it perform Unicode normalization or context-aware percent-encoding normalization.
     *
     * For maximum flexibility the username and password can be set independently. When in doubt, set both.
     *
     * @param string $url The URL to normalize
     * @param ?string $u Username to add to the URL, replacing any existing credentials; passing null will using the existing value, while passing an empty string will clear it
     * @param ?string $p Password to add to the URL, if a username is specified; passing null will using the existing value, while passing an empty string will clear it
     */
    public static function normalize(string $url, ?string $u = null, ?string $p = null): string {
        $credValid = true;
        if (substr($url, 0, 2) === "//") {
            $prefix = "http:";
        } elseif (substr($url, 0, 1) === "/") {
            $prefix = "http://a";
            $credValid = false;
        } else {
            $prefix = "";
        }
        $parsed = WebUrl::parse("$prefix$url");
        if ($parsed) {
            if ($credValid) {
                if ($u !== null) {
                    $parsed = $parsed->withUsername($u);
                }
                if ($p !== null) {
                    $parsed = $parsed->withPassword($p);
                }
            }
            $parsed = $parsed->withFragment(null);
            if ($parsed->getQuery() === "") {
                $parsed = $parsed->withQuery(null);
            }
            $url = substr($parsed->toAsciiString(), strlen($prefix));
        }
        return preg_replace('/%(?![0-9A-F]{2})/', "%25", RfcParser::normalize($url));
    }

    /** Appends data to a URL's query component
     *
     * @param string $url The input URL
     * @param string $data The data to append. This should already be escaped where necessary and not start with any delimiter
     * @param string $glue The query subcomponent delimiter, usually "&". If the URL has no query, "?" will be prepended instead
     */
    public static function queryAppend(string $url, string $data, string $glue = "&"): string {
        if (!strlen($data)) {
            return $url;
        }
        $insPos = strpos($url, "#");
        $insPos = $insPos === false ? strlen($url) : $insPos;
        $qPos = strpos($url, "?");
        $hasQuery = $qPos !== false;
        $glue = $hasQuery ? $glue : "?";
        if ($hasQuery && $insPos > 0) {
            if ($url[$insPos - 1] === $glue || ($insPos - 1) == $qPos) {
                // if the URL already has excess glue, use it
                $glue = "";
            }
        }
        return substr($url, 0, $insPos).$glue.$data.substr($url, $insPos);
    }

    /** Reads credentials from the souce URL and inserts them into the destination URL, if origins match. 
     * 
     * If there are no credentials or the origins do not match, the destination URL is returned without modification
     */
    public static function credentialsApply(string $destination, string $source): string {
        $s = parse_url(self::normalize($source));
        if (strlen($s['user'] ?? "")) {
            $d = parse_url(self::normalize($destination));
            // the origin constitutes a security boundary
            if (
                ($d['scheme'] ?? "") === ($s['scheme'] ?? "")
                && ($d['host'] ?? "") === ($s['host'] ?? "")
                && ($d['port'] ?? null) === ($s['port'] ?? null)
            ) {
                return self::normalize($destination, $s['user'], $s['pass'] ?? "");
            }
        }
        return $destination;
    }

    public static function origin(string $url): string {
        $origin = trim($url);
        if ($origin === "null") {
            // if the origin is the special value "null", use it
            return "null";
        }
        $parsed = WebUrl::parse($origin);
        if ($parsed) {
            $port = $parsed->getPort();
            return $parsed->getScheme()."://".strtolower($parsed->getAsciiHost()).($port !== null ? ":$port" : "");
        } else {
            return "";
        }
    }
}
