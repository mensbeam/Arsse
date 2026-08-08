<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use MensBeam\Mime\MimeType;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractHandler implements Handler {
    abstract public function dispatch(ServerRequestInterface $req): ResponseInterface;

    protected function now(): \DateTimeImmutable {
        return Arsse::$obj->get(\DateTimeImmutable::class)->setTimezone(new \DateTimeZone("UTC"));
    }

    protected function shouldChallenge(ServerRequestInterface $req): bool {
        if ($req->getAttribute("authenticationFailed", false)) {
            return true;
        } elseif (Arsse::$conf->userHTTPAuthRequired || Arsse::$conf->userPreAuth) {
            if ($req->getAttribute("authenticated", false)) {
                return false;
            }
            return true;
        }
        return false;
    }

    protected function isAdmin(): bool {
        return (bool) Arsse::$user->propertiesGet(Arsse::$user->id)['admin'];
    }

    protected function fieldMapNames(array $data, array $map): array {
        $out = [];
        foreach ($map as $to => $from) {
            // we ignore missing keys because Tiny Tiny RSS is sometimes inconsistent about arrays of objects all having the same keys
            if (array_key_exists($from, $data)) {
                $out[$to] = $data[$from];
            }
        }
        return $out;
    }

    protected function fieldMapTypes(array $data, array $map, string $dateFormat = "sql"): array {
        foreach ($map as $key => $type) {
            // we ignore missing keys because Tiny Tiny RSS is sometimes inconsistent about arrays of objects all having the same keys
            if (array_key_exists($key, $data)) {
                if ($type === "datetime" && $dateFormat !== "sql") {
                    $data[$key] = Date::transform($data[$key], $dateFormat, "sql");
                } else {
                    settype($data[$key], $type);
                }
            }
        }
        return $data;
    }

    public function bodyParse(MessageInterface $msg): array {
        $type = MimeType::extract($msg->getHeaderLine("Content-Type"));
        $body = (string) $msg->getBody();
        if (!strlen($body)) {
            return [];
        }
        switch ($type->essence ?? "") {
            case "application/json":
            case "text/json":
                try {
                    $out = HTTP::parseJson($body);
                } catch (\JsonException $e) {
                    throw new Exception400;
                }
                if (!is_array($out)) {
                    throw new Exception422;
                }
                return $out;
            case "application/x-www-form-urlencoded":
                if (HTTP::sniffJson($body)) {
                    try {
                        return HTTP::parseJson($body);
                    } catch (\JsonException $e) {
                        throw new Exception422;
                    }
                }
                return HTTP::parseParams($body, true);
            case "multipart/form-data":
                $out = HTTP::parseMultipart($body, $type->params['boundary'] ?? "");
                if ($out === null) {
                    throw new Exception400;
                }
                return $out;
            case "":
                if (HTTP::sniffJson($body)) {
                    try {
                        return HTTP::parseJson($body);
                    } catch (\JsonException $e) {
                        throw new Exception400;
                    }
                } elseif (HTTP::sniffParams($body)) {
                    return HTTP::parseParams($body, true);
                }
                throw new Exception400;
            default:
                // other media types would normally be rejected, but 
                //   if it happens to be mislabelled JSON we can accept
                //   it; we will not try form data here, though,
                //   because it's not a very distinct format; multipart
                //   must also be rejected because we need the boundary
                if (HTTP::sniffJson($body)) {
                    try {
                        return HTTP::parseJson($body);
                    } catch (\JsonException $e) {}
                }
                throw new Exception415;
        }
    }
}
