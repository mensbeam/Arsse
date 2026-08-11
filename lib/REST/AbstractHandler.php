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

    public function bodyParse(ServerRequestInterface $msg, bool $flat = false): array {
        $type = MimeType::extract($msg->getHeaderLine("Content-Type"));
        $body = (string) $msg->getBody();
        $out = [];
        switch ($type->essence ?? "") {
            case "application/json":
            case "text/json":
                if ($body === "") {
                    return $out;
                }
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
                $out = HTTP::parseParams($body, true);
                break;
            case "multipart/form-data":
                // PHP has automatic handling of multipart/form-data for POST
                //   requests to populate the $_POST and $_FILES arrays. This
                //   handling discards the request body, so we must rely on
                //   PHP's non-conforming parsing here whether we like it or
                //   not. This necessarily limits us to single-value keys.
                if ($body === "" && $msg->getMethod() === "POST") {
                    return (array) $msg->getParsedBody();
                }
                $out = HTTP::parseMultipart($body, $type->params['boundary'] ?? "");
                break;
            case "":
                if (HTTP::sniffJson($body)) {
                    try {
                        return HTTP::parseJson($body);
                    } catch (\JsonException $e) {
                        throw new Exception400;
                    }
                } elseif (HTTP::sniffParams($body)) {
                    $out = HTTP::parseParams($body, true);
                } elseif ($body === "") {
                    return $out; // @codeCoverageIgnore
                } else {
                    throw new Exception400;
                }
                break;
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
        if ($flat) {
            $out = array_map(function($v) {
                return is_array($v) ? array_pop($v) : $v;
            }, $out);
        }
        return $out;
    }
}
