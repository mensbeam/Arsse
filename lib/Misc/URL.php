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

    /** Returns whether a URL is absolute i.e. has a scheme */
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
     * - Discarding empty path segments
     * - Discarding empty queries
     * - Generic percent-encoding normalization
     * - Fragment discarding
     *
     * It does NOT drop trailing slashes from paths, nor does it perform Unicode normalization or context-aware percent-encoding normalization
     *
     * @param string $url The URL to normalize
     * @param string $u Username to add to the URL, replacing any existing credentials
     * @param string $p Password to add to the URL, if a username is specified
     */
    public static function normalize(string $url, string $u = null, string $p = null): string {
        extract(parse_url($url));
        $out = "";
        if (isset($scheme)) {
            $out .= strtolower($scheme).":";
        }
        if (isset($host)) {
            $out .= "//";
            if (strlen($u ?? "")) {
                $out .= self::normalizeEncoding(rawurlencode($u));
                if (strlen($p ?? "")) {
                    $out .= ":".self::normalizeEncoding(rawurlencode($p));
                }
                $out .= "@";
            } elseif (strlen($user ?? "")) {
                $out .= self::normalizeEncoding($user);
                if (strlen($pass ?? "")) {
                    $out .= ":".self::normalizeEncoding($pass);
                }
                $out .= "@";
            }
            $out .= self::normalizeHost($host);
            $out .= isset($port) ? ":$port" : "";
        }
        $out .= self::normalizePath($path ?? "", isset($host));
        if (isset($query) && strlen($query)) {
            $out .= "?".self::normalizeEncoding($query);
        }
        return $out;
    }

    /** Perform percent-encoding normalization for a given URL component */
    protected static function normalizeEncoding(string $part): string {
        $pos = 0;
        $end = strlen($part);
        $out = "";
        // process each character in sequence
        while ($pos < $end) {
            $c = $part[$pos];
            if ($c === "%") {
                // the % character signals an encoded character...
                $d = substr($part, $pos + 1, 2);
                if (!preg_match("/^[0-9a-fA-F]{2}$/", $d)) {
                    // unless there are fewer than two characters left in the string or the two characters are not hex digits
                    $d = ord($c);
                } else {
                    $d = hexdec($d);
                    $pos += 2;
                }
            } else {
                $d = ord($c);
            }
            $dc = chr($d);
            if ($d < 0x21 || $d > 0x7E || $d == 0x25) {
                // these characters are always encoded
                $out .= "%".strtoupper(dechex($d));
            } elseif (preg_match("/[a-zA-Z0-9\._~-]/", $dc)) {
                // these characters are never encoded
                $out .= $dc;
            } else {
                // these characters are passed through as-is
                if ($c === "%") {
                    $out .= "%".strtoupper(dechex($d));
                } else {
                    $out .= $c;
                }
            }
            $pos++;
        }
        return $out;
    }

    /** Normalizes a hostname per IDNA:2008 */
    protected static function normalizeHost(string $host): string {
        if ($host[0] === "[" && substr($host, -1) === "]") {
            // normalize IPv6 addresses
            $addr = @inet_pton(substr($host, 1, strlen($host) - 2));
            if ($addr !== false) {
                return "[".inet_ntop($addr)."]";
            }
        }
        $idn = idn_to_ascii($host, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);
        return $idn !== false ? idn_to_utf8($idn, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46) : $host;
    }

    /** Normalizes the whole path segment to remove empty segments and relative segments */
    protected static function normalizePath(string $path, bool $hasHost): string {
        $parts = explode("/", self::normalizeEncoding($path));
        $absolute = ($hasHost || $path[0] === "/");
        $index = (substr($path, -1) === "/");
        $out = [];
        foreach ($parts as $p) {
            switch ($p) {
                case "":
                case ".":
                    break;
                case "..":
                    array_pop($out);
                    break;
                default:
                    $out[] = $p;
            }
        }
        $out = implode("/", $out);
        $out = ($absolute ? "/" : "").$out.($index ? "/" : "");
        return str_replace("//", "/", $out);
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
}
