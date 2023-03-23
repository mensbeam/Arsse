<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractHandler implements Handler {
    abstract public function __construct();
    abstract public function dispatch(ServerRequestInterface $req): ResponseInterface;

    protected function now(): \DateTimeImmutable {
        return Arsse::$obj->get(\DateTimeImmutable::class)->setTimezone(new \DateTimeZone("UTC"));
    }

    protected function isAdmin(): bool {
        return (bool) Arsse::$user->propertiesGet(Arsse::$user->id, false)['admin'];
    }

    protected function fieldMapNames(array $data, array $map): array {
        $out = [];
        foreach ($map as $to => $from) {
            if (array_key_exists($from, $data)) {
                $out[$to] = $data[$from];
            }
        }
        return $out;
    }

    protected function fieldMapTypes(array $data, array $map, string $dateFormat = "sql"): array {
        foreach ($map as $key => $type) {
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
}
