<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\REST;
use JKingWeb\Arsse\REST\Handler;
use JKingWeb\Arsse\REST\Exception501;
use JKingWeb\Arsse\REST\NextCloudNews\V1_2 as NCN;
use JKingWeb\Arsse\REST\TinyTinyRSS\API as TTRSS;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\TextResponse;
use Zend\Diactoros\Response\EmptyResponse;
use Phake;

/** @covers \JKingWeb\Arsse\REST */
class TestREST extends \JKingWeb\Arsse\Test\AbstractTest {

    /** @dataProvider provideApiMatchData */
    public function testMatchAUrlToAnApi($apiList, string $input, array $exp) {
        $r = new REST($apiList);
        try {
            $out = $r->apiMatch($input);
        } catch (Exception501 $e) {
            $out = [];
        }
        $this->assertEquals($exp, $out);
    }

    public function provideApiMatchData() {
        $real = null;
        $fake = [
            'unstripped' => ['match' => "/full/url", 'strip' => "", 'class' => "UnstrippedProtocol"],
        ];
        return [
            [$real, "/index.php/apps/news/api/v1-2/feeds", ["ncn_v1-2",    "/feeds",     \JKingWeb\Arsse\REST\NextCloudNews\V1_2::class]],
            [$real, "/index.php/apps/news/api/v1-2",       ["ncn",         "/v1-2",      \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
            [$real, "/index.php/apps/news/api/",           ["ncn",         "/",          \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
            [$real, "/index%2Ephp/apps/news/api/",         ["ncn",         "/",          \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
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
    public function testAuthenticateRequests(array $serverParams, array $expAttr) {
        $r = new REST();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->auth->thenReturn(true);
        Phake::when(Arsse::$user)->auth($this->anything(), "superman")->thenReturn(false);
        Phake::when(Arsse::$user)->auth("jane.doe@example.com", $this->anything())->thenReturn(false);
        // create an input server request
        $req = new ServerRequest($serverParams);
        // create the expected output
        $exp = $req;
        foreach ($expAttr as $key => $value) {
            $exp = $exp->withAttribute($key, $value);
        }
        $act = $r->authenticateRequest($req);
        $this->assertMessage($exp, $act);
    }

    public function provideAuthenticableRequests() {
        return [
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "secret"],                                          ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "secret", 'REMOTE_USER' => "jane.doe@example.com"], ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['PHP_AUTH_USER' => "jane.doe@example.com", 'PHP_AUTH_PW' => "secret"],                                          []],
            [['PHP_AUTH_USER' => "john.doe@example.com", 'PHP_AUTH_PW' => "superman"],                                        []],
            [['REMOTE_USER' => "john.doe@example.com"],                                                                       ['authenticated' => true, 'authenticatedUser' => "john.doe@example.com"]],
            [['REMOTE_USER' => "someone.else@example.com"],                                                                   ['authenticated' => true, 'authenticatedUser' => "someone.else@example.com"]],
            [['REMOTE_USER' => "jane.doe@example.com"],                                                                       []],
        ];
    }

    public function testSendAuthenticationChallenges() {
        $this->setConf();
        $r = new REST();
        $in = new EmptyResponse(401);
        $exp = $in->withHeader("WWW-Authenticate", 'Basic realm="OOK"');
        $act = $r->challenge($in, "OOK");
        $this->assertMessage($exp, $act);
        $exp = $in->withHeader("WWW-Authenticate", 'Basic realm="'.Arsse::$conf->httpRealm.'"');
        $act = $r->challenge($in);
        $this->assertMessage($exp, $act);
    }
    
    /** @dataProvider provideUnnormalizedOrigins */
    public function testNormalizeOrigins(string $origin, string $exp, array $ports = null) {
        $r = new REST();
        $act = $r->corsNormalizeOrigin($origin, $ports);
        $this->assertSame($exp, $act);
    }

    public function provideUnnormalizedOrigins() {
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
    public function testNegotiateCors($origin, bool $exp, string $allowed = null, string $denied = null) {
        $this->setConf();
        $r = Phake::partialMock(REST::class);
        Phake::when($r)->corsNormalizeOrigin->thenReturnCallback(function ($origin) {
            return $origin;
        });
        $req = new Request("", "GET", "php://memory", ['Origin' => $origin]);
        $act = $r->corsNegotiate($req, $allowed, $denied);
        $this->assertSame($exp, $act);
    }

    public function provideCorsNegotiations() {
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
            [[],                         false, "*",               ""              ],
        ];
    }

    /** @dataProvider provideCorsHeaders */
    public function testAddCorsHeaders(string $reqMethod, array $reqHeaders, array $resHeaders, array $expHeaders) {
        $r = new REST();
        $req = new Request("", $reqMethod, "php://memory", $reqHeaders);
        $res = new EmptyResponse(204, $resHeaders);
        $exp = new EmptyResponse(204, $expHeaders);
        $act = $r->corsApply($res, $req);
        $this->assertMessage($exp, $act);
    }

    public function provideCorsHeaders() {
        return [
            ["GET", ['Origin' => "null"], [], [
                'Access-Control-Allow-Origin' => "null",
                'Access-Control-Allow-Credentials' => "true",
                'Vary' => "Origin",
            ]],
            ["GET", ['Origin' => "http://example"], [], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary' => "Origin",
            ]],
            ["GET", ['Origin' => "http://example"], ['Content-Type' => "text/plain; charset=utf-8"], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary' => "Origin",
                'Content-Type' => "text/plain; charset=utf-8",
            ]],
            ["GET", ['Origin' => "http://example"], ['Vary' => "Content-Type"], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Vary' => ["Content-Type", "Origin"],
            ]],
            ["OPTIONS", ['Origin' => "http://example"], [], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Max-Age' => (string) (60 *60 *24),
                'Vary' => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example"], ['Allow' => "GET, PUT, HEAD, OPTIONS"], [
                'Allow' => "GET, PUT, HEAD, OPTIONS",
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Methods' => "GET, PUT, HEAD, OPTIONS",
                'Access-Control-Max-Age' => (string) (60 *60 *24),
                'Vary' => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example", 'Access-Control-Request-Headers' => "Content-Type, If-None-Match"], [], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Headers' => "Content-Type, If-None-Match",
                'Access-Control-Max-Age' => (string) (60 *60 *24),
                'Vary' => "Origin",
            ]],
            ["OPTIONS", ['Origin' => "http://example", 'Access-Control-Request-Headers' => ["Content-Type", "If-None-Match"]], [], [
                'Access-Control-Allow-Origin' => "http://example",
                'Access-Control-Allow-Credentials' => "true",
                'Access-Control-Allow-Headers' => "Content-Type,If-None-Match",
                'Access-Control-Max-Age' => (string) (60 *60 *24),
                'Vary' => "Origin",
            ]],
        ];
    }

    /** @dataProvider provideUnnormalizedResponses */
    public function testNormalizeHttpResponses(ResponseInterface $res, ResponseInterface $exp, RequestInterface $req = null) {
        $r = Phake::partialMock(REST::class);
        Phake::when($r)->corsNegotiate->thenReturn(true);
        Phake::when($r)->challenge->thenReturnCallback(function ($res) {
            return $res->withHeader("WWW-Authenticate", "Fake Value");
        });
        Phake::when($r)->corsApply->thenReturnCallback(function ($res) {
            return $res;
        });
        $act = $r->normalizeResponse($res, $req);
        $this->assertMessage($exp, $act);
    }

    public function provideUnnormalizedResponses() {
        $stream = fopen("php://memory", "w+b");
        fwrite($stream, "ook");
        return [
            [new EmptyResponse(204),                                          new EmptyResponse(204)],
            [new EmptyResponse(401),                                          new EmptyResponse(401, ['WWW-Authenticate' => "Fake Value"])],
            [new EmptyResponse(204, ['Allow' => "PUT"]),                      new EmptyResponse(204, ['Allow' => "PUT, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => "PUT, OPTIONS"]),             new EmptyResponse(204, ['Allow' => "PUT, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => "PUT,OPTIONS"]),              new EmptyResponse(204, ['Allow' => "PUT, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => ["PUT", "OPTIONS"]]),         new EmptyResponse(204, ['Allow' => "PUT, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => ["PUT, DELETE", "OPTIONS"]]), new EmptyResponse(204, ['Allow' => "PUT, DELETE, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => "HEAD,GET"]),                 new EmptyResponse(204, ['Allow' => "HEAD, GET, OPTIONS"])],
            [new EmptyResponse(204, ['Allow' => "GET"]),                      new EmptyResponse(204, ['Allow' => "GET, HEAD, OPTIONS"])],
            [new TextResponse("ook", 200),                                    new TextResponse("ook", 200, ['Content-Length' => "3"])],
            [new TextResponse("", 200),                                       new TextResponse("", 200, ['Content-Length' => "0"])],
            [new TextResponse("ook", 404),                                    new TextResponse("ook", 404, ['Content-Length' => "3"])],
            [new TextResponse("", 404),                                       new TextResponse("", 404)],
            [new Response($stream, 200),                                      new Response($stream, 200, ['Content-Length' => "3"]),   new Request("", "GET")],
            [new Response($stream, 200),                                      new EmptyResponse(200, ['Content-Length' => "3"]),       new Request("", "HEAD")],
        ];
    }

    public function testCreateHandlers() {
        $r = new REST();
        foreach (REST::API_LIST as $api) {
            $class = $api['class'];
            $this->assertInstanceOf(Handler::class, $r->getHandler($class));
        }
    }

    /** @dataProvider provideMockRequests */
    public function testDispatchRequests(ServerRequest $req, string $method, bool $called, string $class = "", string $target ="") {
        $r = Phake::partialMock(REST::class);
        Phake::when($r)->normalizeResponse->thenReturnCallback(function ($res) {
            return $res;
        });
        Phake::when($r)->authenticateRequest->thenReturnCallback(function ($req) {
            return $req;
        });
        if ($called) {
            $h = Phake::mock($class);
            Phake::when($r)->getHandler($class)->thenReturn($h);
            Phake::when($h)->dispatch->thenReturn(new EmptyResponse(204));
        }
        $out = $r->dispatch($req);
        $this->assertInstanceOf(ResponseInterface::class, $out);
        if ($called) {
            Phake::verify($r)->authenticateRequest;
            Phake::verify($h)->dispatch(Phake::capture($in));
            $this->assertSame($method, $in->getMethod());
            $this->assertSame($target, $in->getRequestTarget());
        } else {
            $this->assertSame(501, $out->getStatusCode());
        }
        Phake::verify($r)->apiMatch;
        Phake::verify($r)->normalizeResponse;
    }

    public function provideMockRequests() {
        return [
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "GET"),  "GET",  true, NCN::class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "HEAD"), "GET",  true, NCN::class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "get"),  "GET",  true, NCN::class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "head"), "GET",  true, NCN::class, "/feeds"],
            [new ServerRequest([], [], "/tt-rss/api/", "POST"),                        "POST", true, TTRSS::class, "/"],
            [new ServerRequest([], [], "/no/such/api/", "HEAD"),                       "GET",  false],
            [new ServerRequest([], [], "/no/such/api/", "GET"),                        "GET",  false],
        ];
    }
}