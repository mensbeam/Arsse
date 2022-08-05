<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\TextResponse;

class Status extends \JKingWeb\Arsse\REST\AbstractHandler {
    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $target = parse_url($req->getRequestTarget())['path'] ?? "";
        if (!in_array($target, ["/version", "/healthcheck"])) {
            return HTTP::respEmpty(404);
        }
        $method = $req->getMethod();
        if ($method === "OPTIONS") {
            return HTTP::respEmpty(204, ['Allow'  => "HEAD, GET"]);
        } elseif ($method !== "GET") {
            return HTTP::respEmpty(405, ['Allow'  => "HEAD, GET"]);
        }
        $out = "";
        if ($target === "/version") {
            $out = V1::VERSION;
        } elseif ($target === "/healthcheck") {
            $out = "OK";
        }
        return new TextResponse($out);
    }
}
