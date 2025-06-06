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
}
