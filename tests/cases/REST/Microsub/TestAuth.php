<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Microsub;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;

/** @covers \JKingWeb\Arsse\REST\Microsub\Auth<extended> */
class TestAuth extends \JKingWeb\Arsse\Test\AbstractTest {
    public function req(string $url, string $method = "GET", array $data = [], array $headers = [], string $type = "application/x-www-form-urlencoded", string $body = null): ResponseInterface {
        $type = (strtoupper($method) === "GET") ? "" : $type;
        $req = $this->serverRequest($method, $url, "/u/", $headers, [], $body ?? $data, $type);
        return (new \JKingWeb\Arsse\REST\Microsub\Auth)->dispatch($req);
    }

    /** @dataProvider provideInvalidRequests */
    public function testHandleInvalidRequests(ResponseInterface $exp, string $method, string $url, string $type = null) {
        $act = $this->req("http://example.com".$url, $method, [], [], $type ?? "");
        $this->assertMessage($exp, $act);
    }

    public function provideInvalidRequests() {
        $r404 = new EmptyResponse(404);
        $r405g = new EmptyResponse(405, ['Allow' => "GET"]);
        $r405gp = new EmptyResponse(405, ['Allow' => "GET,POST"]);
        $r415 = new EmptyResponse(415, ['Accept' => "application/x-www-form-urlencoded"]);
        return [
            [$r404,   "GET",  "/u/"],
            [$r404,   "GET",  "/u/john.doe/hello"],
            [$r404,   "GET",  "/u/john.doe/"],
            [$r404,   "GET",  "/u/john.doe?f=hello"],
            [$r404,   "GET",  "/u/?f="],
            [$r404,   "GET",  "/u/?f=goodbye"],
            [$r405g,  "POST", "/u/john.doe"],
            [$r405gp, "PUT",  "/u/?f=token"],
            [$r404,   "POST", "/u/john.doe?f=token"],
            [$r415,   "POST", "/u/?f=token", "application/json"],
        ];
    }

    /** @dataProvider provideOptionsRequests */
    public function testHandleOptionsRequests(string $url, array $headerFields) {
        $exp = new EmptyResponse(204, $headerFields);
        $this->assertMessage($exp, $this->req("http://example.com".$url, "OPTIONS"));
    }

    public function provideOptionsRequests() {
        $ident = ['Allow' => "GET"];
        $other = ['Allow' => "GET,POST", 'Accept' => "application/x-www-form-urlencoded"];
        return [
            ["/u/john.doe", $ident],
            ["/u/?f=token", $other],
            ["/u/?f=auth",  $other],
        ];
    }

    /** @dataProvider provideDiscoveryRequests */
    public function testDiscoverAUser(string $url, string $origin) {
        $auth = $origin."/u/?f=auth";
        $token = $origin."/u/?f=token";
        $microsub = $origin."/microsub";
        $exp = new HtmlResponse('<meta charset="UTF-8"><link rel="authorization_endpoint" href="'.htmlspecialchars($auth).'"><link rel="token_endpoint" href="'.htmlspecialchars($token).'"><link rel="microsub" href="'.htmlspecialchars($microsub).'">', 200, ['Link' => [
            "<$auth>; rel=\"authorization_endpoint\"",
            "<$token>; rel=\"token_endpoint\"",
            "<$microsub>; rel=\"microsub\"",
        ]]);
        $this->assertMessage($exp, $this->req($url));
    }

    public function provideDiscoveryRequests() {
        return [
            ["http://example.com/u/john.doe",      "http://example.com"],
            ["http://example.com:80/u/john.doe",   "http://example.com"],
            ["https://example.com/u/john.doe",     "https://example.com"],
            ["https://example.com:443/u/john.doe", "https://example.com"],
            ["http://example.com:443/u/john.doe",  "http://example.com:443"],
            ["https://example.com:80/u/john.doe",  "https://example.com:80"],
        ];
    }
}
