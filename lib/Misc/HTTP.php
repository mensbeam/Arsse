<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

class HTTP {
    public static function matchType(MessageInterface $msg, string ...$type): bool {
        $header = $msg->getHeaderLine("Content-Type") ?? "";
        foreach ($type as $t) {
            $pattern = "/^".preg_quote(trim($t), "/")."\s*($|;|,)/Di";
            if (preg_match($pattern, $header)) {
                return true;
            }
        }
        return false;
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
