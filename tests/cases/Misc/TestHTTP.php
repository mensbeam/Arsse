<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\HTTP;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/** @covers \JKingWeb\Arsse\Misc\HTTP */
class TestHTTP extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideMediaTypes */
    public function testMatchMediaType(string $header, array $types, bool $exp): void {
        $msg = (new Request("POST", "/"))->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, ...$types));
        $msg = (new Response)->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, ...$types));
    }

    public function provideMediaTypes(): array {
        return [
            ["application/json",         ["application/json"],              true],
            ["APPLICATION/JSON",         ["application/json"],              true],
            ["text/JSON",                ["application/json", "text/json"], true],
            ["text/json; charset=utf-8", ["application/json", "text/json"], true],
            ["",                         ["application/json"],              false],
            ["",                         ["application/json", ""],          true],
            ["application/json ;",       ["application/json"],              true],
            ["application/feed+json",    ["application/json", "+json"],     true],
            ["application/xhtml+xml",    ["application/json", "+json"],     false],
        ];
    }

    /** @dataProvider provideTypedMessages */
    public function testCreateResponses(string $type, array $params, ResponseInterface $exp): void {
        $act = call_user_func(["JKingWeb\\Arsse\\Misc\\HTTP", $type], ...$params);
        $this->assertMessage($exp, $act);
    }

    public function provideTypedMessages(): iterable {
        return [
            ["respEmpty", [422, ['Content-Length' => "0"]],                                     new Response(422, ['Content-Length' => "0"])],
            ["respText",  ["OOK"],                                                              new Response(200, ['Content-Type' => "text/plain; charset=UTF-8"], "OOK")],
            ["respText",  ["OOK", 201, ['Content-Type' => "application/octet-stream"]],         new Response(201, ['Content-Type' => "application/octet-stream"], "OOK")],
            ["respJson",  [['ook' => "eek"]],                                                   new Response(200, ['Content-Type' => "application/json"], '{"ook":"eek"}')],
            ["respJson",  [['ook' => "eek"], 400, ['Content-Type' => "application/feed+json"]], new Response(400, ['Content-Type' => "application/feed+json"], '{"ook":"eek"}')],
            ["respXml",   ["<html/>"],                                                          new Response(200, ['Content-Type' => "application/xml; charset=UTF-8"], "<html/>")],
            ["respXml",   ["<html/>", 451, ['Content-Type' => "text/plain", 'Vary' => "ETag"]], new Response(451, ['Content-Type' => "text/plain", 'Vary' => "ETag"], "<html/>")],
        ];
    }
}
