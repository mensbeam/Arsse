<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Misc;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use JKingWeb\Arsse\Arsse;
use MensBeam\Mime\MimeType;

class HTTP {
    /** Matches the Content-Type of a message against an array of allowed types */
    public static function matchType(MessageInterface $msg, array $types, bool $allowEmpty = true): bool {
        $header = MimeType::extract($msg->getHeaderLine("Content-Type"));
        if (!$header) {
            return $allowEmpty;
        } elseif (MimeType::negotiate([(string) $header], $types) !== null) {
            return true;
        }
        return false;
    }

    /** Inserts any universal HTTP authentication challenges suported by The Arsse into the provided response and returns the new response */
    public static function challenge(ResponseInterface $res): ResponseInterface {
        $realm = Arsse::$conf ? Arsse::$conf->httpRealm : "The Advanced RSS Environment";
        return $res->withAddedHeader("WWW-Authenticate", 'Basic realm="'.$realm.'", charset="UTF-8"');
    }

    /** Checks whether the provided username contains any U+003A COLON or control characters, as these are incompatible with HTTP Basic authentication. Returns the first offending character */
    public static function userInvalid(string $username): string {
        preg_match("/[\x{00}-\x{1F}\x{7F}:]/", $username, $m);
        return $m[0] ?? "";
    }

    public static function respEmpty(int $status, ?array $headers = []): ResponseInterface {
        return new Response($status, $headers ?? []);
    }

    public static function respJson($body, int $status = 200, ?array $headers = []): ResponseInterface {
        $headers = ($headers ?? []) + ['Content-Type' => "application/json"];
        return new Response($status, $headers, json_encode($body, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    public static function respText(string $body, int $status = 200, ?array $headers = []): ResponseInterface {
        $headers = ($headers ?? []) + ['Content-Type' => "text/plain; charset=UTF-8"];
        return new Response($status, $headers, $body);
    }

    public static function respXml(string $body, int $status = 200, ?array $headers = []): ResponseInterface {
        $headers = ($headers ?? []) + ['Content-Type' => "application/xml; charset=UTF-8"];
        return new Response($status, $headers, $body);
    }

    /** Parses a set of query parameters or application/x-ww-form-data body
     * 
     * Keys which appear more than once produce array values; keys which have
     * no value delimiter produce null values; keys which have no value after
     * their value delimiter produce empty-string values.
     * 
     * @param string $data The data to parse
     * @param bool $body Whether the data is an application/x-www-form-data entity body
     */
    public static function parseParams(string $data, bool $body): array {
        $out = [];
        if (!strlen($data)) {
            return $out;
        }
        $data = explode("&", $data);
        foreach ($data as $d) {
            $d = explode("=", $d, 2);
            $k = $body ? urldecode($d[0]) : rawurldecode($d[0]);
            $v = $d[1] ?? null;
            if (array_key_exists($k, $out)) {
                if (!is_array($out[$k])) {
                    $out[$k] = [$out[$k]];
                }
                $out[$k][] = $v === null ? null : ($body ? urldecode($v) : rawurldecode($v));
            } else {
                $out[$k] = $v === null ? null : ($body ? urldecode($v) : rawurldecode($v));
            }
        }
        return $out;
    }

    /** Parses a multipart/form-data entity
     * 
     * Severe shortcuts are taken, namely:
     * 
     * - Data is assumed to be all text fields
     * - Data is assumed to be UTF-8 text
     * - Parts' Content-Disposition is assumed to have the name as the first parameter
     * - Field names are assumed not to require percent-decoding
     * - Content-Transfer-Encoding is assumed not to be used
     * 
     * This is enough to handle known clients.
     */
    public static function parseMultipart(string $data, string $boundary): array {
        $out = [];
        if ($boundary === "" || $data === "") {
            return $out;
        }
        $data = preg_split("/\r\n/", $data);
        while ($data && strpos($data[0], "--$boundary") !== 0) {
            array_shift($data);
        }
        $name = null;
        $value = [];
        $inBody = false;
        foreach ($data as $l) {
            if (strpos($l, "--$boundary") === 0) {
                if ($name !== null) {
                    $value = implode("\r\n", $value);
                    if (!isset($out[$name])) {
                        $out[$name] = $value;
                    } elseif (!is_array($out[$name])) {
                        $out[$name] = [$out[$name], $value];
                    } else {
                        $out[$name][] = $value;
                    }
                }
                $name = null;
                $inBody = false;
                $value = [];
                if (strpos($l, "--$boundary--") === 0) {
                    break;
                }
            } elseif ($inBody) {
                $value[] = $l;
            } elseif ($l === "") {
                $inBody = true;
            } elseif (preg_match('/^Content-Disposition:\s*form-data\s*;\s*name=("[^"]*"|[^ \t;]*)/i', $l, $m)) {
                $name = $m[1];
                if ($name[0] === '"') {
                    $name = substr($name, 1, strlen($name) - 2);
                }
            }
        }
        return $out;
    }

    public static function parseJson(string $data) {
        return json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
    }

    public static function sniffJson(string $data): bool {
        return (bool) preg_match('/^\s*\{\s*"[a-zA-Z_]+"\s*:/s', $data);
    }

    public static function sniffParams(string $data): bool {
        return (bool) preg_match('/^[a-zA-Z_]+=/s', $data);
    }
}
