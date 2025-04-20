<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\HTTP;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JKingWeb\Arsse\Arsse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\Misc\HTTP::class)]
class TestHTTP extends \JKingWeb\Arsse\Test\AbstractTest {
    #[DataProvider('provideMediaTypes')]
    public function testMatchMediaType(string $header, array $types, bool $exp): void {
        $msg = (new Request("POST", "/"))->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, $types));
        $msg = (new Response)->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, $types));
    }

    public static function provideMediaTypes(): array {
        return [
            ["application/json",         ["application/json"],              true],
            ["APPLICATION/JSON",         ["application/json"],              true],
            ["text/JSON",                ["application/json", "text/json"], true],
            ["text/json; charset=utf-8", ["application/json", "text/json"], true],
            ["",                         ["application/json"],              true],
            ["application/json ;",       ["application/json"],              true],
        ];
    }


    #[DataProvider('provideTypedMessages')]
    public function testCreateResponses(string $type, array $params, ResponseInterface $exp): void {
        $act = call_user_func(["JKingWeb\\Arsse\\Misc\\HTTP", $type], ...$params);
        $this->assertMessage($exp, $act);
    }

    public static function provideTypedMessages(): iterable {
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

    public function testSendAuthenticationChallenges(): void {
        self::setConf();
        $in = new Response();
        $exp = $in->withHeader("WWW-Authenticate", 'Basic realm="'.Arsse::$conf->httpRealm.'", charset="UTF-8"');
        $act = HTTP::challenge($in);
        $this->assertMessage($exp, $act);
    }


    #[DataProvider('provideUserNames')]
    public function testValidateUsernames(string $user, string $exp): void {
        $this->assertSame($exp, HTTP::userInvalid($user));
    }

    public static function provideUserNames(): iterable {
        // output names with control characters
        foreach (array_merge(range(0x00, 0x1F), [0x7F]) as $ord) {
            yield [chr($ord), chr($ord)];
            yield ["john".chr($ord)."doe@example.com", chr($ord)];
        }
        // also handle colons
        yield [":", ":"];
        yield ["john:doe@example.com", ":"];
        // pass through a valid name
        yield ["john.doe@example.com", ""];
    }
}
