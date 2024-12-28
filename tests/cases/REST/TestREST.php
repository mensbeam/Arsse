<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\REST;
use JKingWeb\Arsse\REST\Exception501;
use JKingWeb\Arsse\REST\NextcloudNews\V1_2 as NCN;
use JKingWeb\Arsse\REST\TinyTinyRSS\API as TTRSS;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\ServerRequest;

/** @covers \JKingWeb\Arsse\REST */
class TestREST extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideApiMatchData */
    public function testMatchAUrlToAnApi($apiList, string $input, array $exp): void {
        $r = new REST($apiList);
        try {
            $out = $r->apiMatch($input);
        } catch (Exception501 $e) {
            $out = [];
        }
        $this->assertEquals($exp, $out);
    }

    public static function provideApiMatchData(): iterable {
        $real = null;
        $fake = [
            'unstripped' => ['match' => "/full/url", 'strip' => "", 'class' => "UnstrippedProtocol"],
        ];
        return [
            [$real, "/index.php/apps/news/api/v1-2/feeds", ["ncn_v1-2",    "/feeds",     \JKingWeb\Arsse\REST\NextcloudNews\V1_2::class]],
            [$real, "/index.php/apps/news/api/v1-2",       ["ncn",         "/v1-2",      \JKingWeb\Arsse\REST\NextcloudNews\Versions::class]],
            [$real, "/index.php/apps/news/api/",           ["ncn",         "/",          \JKingWeb\Arsse\REST\NextcloudNews\Versions::class]],
            [$real, "/index%2Ephp/apps/news/api/",         ["ncn",         "/",          \JKingWeb\Arsse\REST\NextcloudNews\Versions::class]],
            [$real, "/index.php/apps/news/",               []],
            [$real, "/index!php/apps/news/api/",           []],
            [$real, "/tt-rss/api/index.php",               ["ttrss_api",   "/index.php", \JKingWeb\Arsse\REST\TinyTinyRSS\API::class]],
            [$real, "/tt-rss/api",                         ["ttrss_api",   "",           \JKingWeb\Arsse\REST\TinyTinyRSS\API::class]],
            [$real, "/tt-rss/API",                         []],
            [$real, "/tt-rss/api-bogus",                   []],
            [$real, "/tt-rss/api bogus",                   []],
            [$real, "/tt-rss/feed-icons/",                 ["ttrss_icon",  "",           \JKingWeb\Arsse\REST\TinyTinyRSS\Icon::class]],
            [$real, "/tt-rss/feed-icons/",                 ["ttrss_icon",  "",           \JKingWeb\Arsse\REST\TinyTinyRSS\Icon::class]],
            [$real, "/tt-rss/feed-icons",                  []],
            [$fake, "/full/url/",                          ["unstripped",  "/full/url/", "UnstrippedProtocol"]],
            [$fake, "/full/url-not",                       []],
        ];
    }

    /** @dataProvider provideAuthenticableRequests */
    public function testAuthenticateRequests(array $serverParams, array $expAttr): void {
        $r = new REST();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(false);
        \Phake::when(Arsse::$user)->auth("john.doe@example.com", "secret")->thenReturn(true);
        \Phake::when(Arsse::$user)->auth("john.doe@example.com", "")->thenReturn(true);
        \Phake::when(Arsse::$user)->auth("someone.else@example.com", "")->thenReturn(true);
        // create an input server request
        $req = new ServerRequest("GET", "/", [], null, "1.1", $serverParams);
        // create the expected output
        $exp = $req;
        foreach ($expAttr as $key => $value) {
            $exp = $exp->withAttribute($key, $value);
        }
        $act = $r->authenticateRequest($req);
        $this->assertMessage($exp, $act);
    }

    public static function provideAuthenticableRequests(): iterable {
        return [
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "secret"],                                          ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "secret", 'REMOTE_USER' => "jane.doe@example.com"], ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['PHP_AUTH_USER' => "jane.doe@example.com", 'PHP_AUTH_PW' => "secret"],                                          ['authenticationFailed' => true]],
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "superman"],                                        ['authenticationFailed' => true]],
            [['REMOTE_USER' => "john.doe@example.com"],                                                                       ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['REMOTE_USER' => "someone.else@example.com"],                                                                   ['authenticated' => true, 'authenticatedUser' => "someone.else@example.com"]],
            [['REMOTE_USER' => "jane.doe@example.com"],                                                                       ['authenticationFailed' => true]],
            [[],                                                                                                              []],
        ];
    }

    public function testSendAuthenticationChallenges(): void {
        self::setConf();
        $r = new REST();
        $in = HTTP::respEmpty(401);
        $exp = $in->withHeader("WWW-Authenticate", 'Basic realm="OOK", charset="UTF-8"');
        $act = $r->challenge($in, "OOK");
        $this->assertMessage($exp, $act);
        $exp = $in->withHeader("WWW-Authenticate", 'Basic realm="'.Arsse::$conf->httpRealm.'", charset="UTF-8"');
        $act = $r->challenge($in);
        $this->assertMessage($exp, $act);
    }

    /** @dataProvider provideUnnormalizedOrigins */
    public function testNormalizeOrigins(string $origin, string $exp, ?array $ports = null): void {
        $r = new REST();
        $act = $r->corsNormalizeOrigin($origin, $ports);
        $this->assertSame($exp, $act);
    }

    public static function provideUnnormalizedOrigins(): iterable {
        return [
            ["null", "null"],
            ["http://example.com",             "http://example.com"],
            ["http://example.com:80",          "http://example.com"],
            ["http://example.com:8%30",        "http://example.com"],
            ["http://example.com:8080",        "http://example.com:8080"],
            ["http://[2001:0db8:0:0:0:0:2:1]", "http://[2001:db8::2:1]"],
            ["http://example",                 "http://example"],
            ["http://ex%41mple",               "http://example"],
            ["http://ex%41mple.co.uk",         "http://example.co.uk"],
            ["http://ex%41mple.co%2euk",       "http://example.co%2Euk"],
            ["http://example/",                ""],
            ["http://example?",                ""],
            ["http://example#",                ""],
            ["http://user@example",            ""],
            ["http://user:pass@example",       ""],
            ["http://[example",                ""],
            ["http://[2bef]",                  ""],
            ["http://example%2F",              "http://example%2F"],
            ["HTTP://example",                 "http://example"],
            ["HTTP://EXAMPLE",                 "http://example"],
            ["%48%54%54%50://example",         "http://example"],
            ["http:%2F%2Fexample",             ""],
            ["https://example",                "https://example"],
            ["https://example:443",            "https://example"],
            ["https://example:80",             "https://example:80"],
            ["ssh://example",                  "ssh://example"],
            ["ssh://example:22",               "ssh://example:22"],
            ["ssh://example:22",               "ssh://example",            ['ssh' => 22]],
            ["SSH://example:22",               "ssh://example",            ['ssh' => 22]],
            ["ssh://example:22",               "ssh://example",            ['ssh' => "22"]],
            ["ssh://example:22",               "ssh://example:22",         ['SSH' => "22"]],
        ];
    }

    /** @dataProvider provideCorsNegotiations */
    public function testNegotiateCors($origin, bool $exp, ?string $allowed = null, ?string $denied = null): void {
        self::setConf();
        $rMock = \Phake::partialMock(REST::class);
        \Phake::when($rMock)->corsNormalizeOrigin->thenReturnCallback(function($origin) {
            return $origin;
        });
        $headers = isset($origin) ? ['Origin' => $origin] : [];
        $req = new Request("GET", "", $headers);
        $act = $rMock->corsNegotiate($req, $allowed, $denied);
        $this->assertSame($exp, $act);
    }

    public static function provideCorsNegotiations(): iterable {
        return [
            ["http://example",           true                                      ],
            ["http://example",           true,  "http://example",  "*"             ],
            ["http://example",           false, "http://example",  "http://example"],
            ["http://example",           false, "https://example", "*"             ],
            ["http://example",           false, "*",               "*"             ],
            ["http://example",           true,  "*",               ""              ],
            ["http://example",           false, "",                ""              ],
            ["null",                     false                                     ],
            ["null",                     true,  "null",            "*"             ],
            ["null",                     false, "null",            "null"          ],
            ["null",                     false, "*",               "*"             ],
            ["null",                     false, "*",               ""              ],
            ["null",                     false, "",                ""              ],
            ["",                         false                                     ],
            ["",                         false, "",                "*"             ],
            ["",                         false, "",                ""              ],
            ["",                         false, "*",               "*"             ],
            ["",                         false, "*",               ""              ],
            [["null", "http://example"], false, "*",               ""              ],
            [null,                       false, "*",               ""              ],
        ];
    }

    /** @dataProvider provideCorsHeaders */
    public function testAddCorsHeaders(string $reqMethod, array $reqHeaders, array $resHeaders, array $expHeaders): void {
        $r = new REST();
        $req = new Request($reqMethod, "php://memory", $reqHeaders);
        $res = HTTP::respEmpty(204, $resHeaders);
        $exp = HTTP::respEmpty(204, $expHeaders);
        $act = $r->corsApply($res, $req);
        $this->assertMessage($exp, $act);
    }

    public static function provideCorsHeaders(): iterable {
        return [
            ["GET", ['Origin' => "null"], [], [
                'Access-Control-Allow-Origin'      => "null",
                'Access-Control-Allow-Credentials' => "true",
                'Vary'                             => "Origin",
            ]],
            ["GET", ['Origin' => "http://example"], [], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary'                             => "Origin",
            ]],
            ["GET", ['Origin' => "http://example"], ['Content-Type' => "text/plain; charset=utf-8"], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary'                             => "Origin",
                'Content-Type'                     => "text/plain; charset=utf-8",
            ]],
            ["GET", ['Origin' => "http://example"], ['Vary' => "Content-Type"], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary'                             => ["Content-Type", "Origin"],
            ]],
            ["OPTIONS", ['Origin' => "http://example"], [], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Max-Age'           => (string) (60 * 60 * 24),
                'Vary'                             => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example"], ['Allow' => "GET, PUT, HEAD, OPTIONS"], [
                'Allow'                            => "GET, PUT, HEAD, OPTIONS",
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Methods'     => "GET, PUT, HEAD, OPTIONS",
                'Access-Control-Max-Age'           => (string) (60 * 60 * 24),
                'Vary'                             => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example", 'Access-Control-Request-Headers' => "Content-Type, If-None-Match"], [], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Headers'     => "Content-Type, If-None-Match",
                'Access-Control-Max-Age'           => (string) (60 * 60 * 24),
                'Vary'                             => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example", 'Access-Control-Request-Headers' => ["Content-Type", "If-None-Match"]], [], [
                'Access-Control-Allow-Origin'      => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Headers'     => "Content-Type, If-None-Match",
                'Access-Control-Max-Age'           => (string) (60 * 60 * 24),
                'Vary'                             => "Origin",
            ]],
        ];
    }

    /** @dataProvider provideUnnormalizedResponses */
    public function testNormalizeHttpResponses(ResponseInterface $res, ResponseInterface $exp, ?RequestInterface $req = null): void {
        $rMock = \Phake::partialMock(REST::class);
        \Phake::when($rMock)->corsNegotiate->thenReturn(true);
        \Phake::when($rMock)->challenge->thenReturnCallback(function($res) {
            return $res->withHeader("WWW-Authenticate", "Fake Value");
        });
        \Phake::when($rMock)->corsApply->thenReturnCallback(function($res) {
            return $res;
        });
        $act = $rMock->normalizeResponse($res, $req);
        $this->assertMessage($exp, $act);
    }

    public static function provideUnnormalizedResponses(): iterable {
        $stream = fopen("php://memory", "w+b");
        fwrite($stream, "ook");
        return [
            [HTTP::respEmpty(204),                                          HTTP::respEmpty(204)],
            [HTTP::respEmpty(401),                                          HTTP::respEmpty(401, ['WWW-Authenticate' => "Fake Value"])],
            [HTTP::respEmpty(204, ['Allow' => "PUT"]),                      HTTP::respEmpty(204, ['Allow' => "PUT, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => "PUT, OPTIONS"]),             HTTP::respEmpty(204, ['Allow' => "PUT, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => "PUT,OPTIONS"]),              HTTP::respEmpty(204, ['Allow' => "PUT, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => ["PUT", "OPTIONS"]]),         HTTP::respEmpty(204, ['Allow' => "PUT, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => ["PUT, DELETE", "OPTIONS"]]), HTTP::respEmpty(204, ['Allow' => "PUT, DELETE, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => "HEAD,GET"]),                 HTTP::respEmpty(204, ['Allow' => "HEAD, GET, OPTIONS"])],
            [HTTP::respEmpty(204, ['Allow' => "GET"]),                      HTTP::respEmpty(204, ['Allow' => "GET, HEAD, OPTIONS"])],
            [HTTP::respText("ook", 200),                                    HTTP::respText("ook", 200, ['Content-Length' => "3"])],
            [HTTP::respText("", 200),                                       HTTP::respText("", 200, ['Content-Length' => "0"])],
            [HTTP::respText("ook", 404),                                    HTTP::respText("ook", 404, ['Content-Length' => "3"])],
            [HTTP::respText("", 404),                                       HTTP::respText("", 404)],
            [new Response(200, [], $stream),                                new Response(200, ['Content-Length' => "3"], $stream),   new Request("GET", "")],
            [new Response(200, [], $stream),                                HTTP::respEmpty(200, ['Content-Length' => "3"]),         new Request("HEAD", "")],
        ];
    }

    /** @dataProvider provideMockRequests */
    public function testDispatchRequests(ServerRequest $req, string $method, bool $called, string $class = "", string $target = ""): void {
        $rMock = \Phake::partialMock(REST::class);
        \Phake::when($rMock)->normalizeResponse->thenReturnCallback(function($res) {
            return $res;
        });
        \Phake::when($rMock)->authenticateRequest->thenReturnCallback(function($req) {
            return $req;
        });
        if ($called) {
            $hMock = \Phake::mock($class);
            \Phake::when($hMock)->dispatch->thenReturn(HTTP::respEmpty(204));
            \Phake::when(Arsse::$obj)->get($class)->thenReturn($hMock);
        }
        $out = $rMock->dispatch($req);
        $this->assertInstanceOf(ResponseInterface::class, $out);
        if ($called) {
            \Phake::verify($rMock, \Phake::atLeast(1))->authenticateRequest(\Phake::anyParameters());
            \Phake::verify($hMock)->dispatch(\Phake::capture($in));
            $this->assertSame($method, $in->getMethod());
            $this->assertSame($target, $in->getRequestTarget());
        } else {
            $this->assertSame(501, $out->getStatusCode());
        }
        \Phake::verify($rMock)->apiMatch(\Phake::anyParameters());
        \Phake::verify($rMock)->normalizeResponse(\Phake::anyParameters());
    }

    public static function provideMockRequests(): iterable {
        return [
            [new ServerRequest("GET", "/index.php/apps/news/api/v1-2/feeds"),  "GET",  true, NCN::class,   "/feeds"],
            [new ServerRequest("GET", "/index.php/apps/news/api/v1-2/feeds"),  "GET",  true, NCN::class,   "/feeds"],
            [new ServerRequest("get", "/index.php/apps/news/api/v1-2/feeds"),  "GET",  true, NCN::class,   "/feeds"],
            [new ServerRequest("head", "/index.php/apps/news/api/v1-2/feeds"), "GET",  true, NCN::class,   "/feeds"],
            [new ServerRequest("POST", "/tt-rss/api/"),                        "POST", true, TTRSS::class, "/"],
            [new ServerRequest("HEAD", "/no/such/api/"),                       "GET",  false],
            [new ServerRequest("GET", "/no/such/api/"),                        "GET",  false],
        ];
    }
}
