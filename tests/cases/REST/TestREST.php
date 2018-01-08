<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST;

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

    /** @dataProvider provideUnnormalizedResponses */
    public function testNormalizeHttpResponses(ResponseInterface $res, ResponseInterface $exp, RequestInterface $req = null) {
        $r = new REST();
        $act = $r->normalizeResponse($res, $req);
        $this->assertResponse($exp, $act);
    }

    public function provideUnnormalizedResponses() {
        $stream = fopen("php://memory", "w+b");
        fwrite($stream,"ook");
        return [
            [new EmptyResponse(204),                                          new EmptyResponse(204)],
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
        if ($called) {
            $h = Phake::mock($class);
            Phake::when($r)->getHandler($class)->thenReturn($h);
            Phake::when($h)->dispatch->thenReturn(new EmptyResponse(204));
        }
        $out = $r->dispatch($req);
        $this->assertInstanceOf(ResponseInterface::class, $out);
        if ($called) {
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
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "GET"),  "GET",  true, NCN::Class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "HEAD"), "GET",  true, NCN::Class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "get"),  "GET",  true, NCN::Class, "/feeds"],
            [new ServerRequest([], [], "/index.php/apps/news/api/v1-2/feeds", "head"), "GET",  true, NCN::Class, "/feeds"],
            [new ServerRequest([], [], "/tt-rss/api/", "POST"),                        "POST", true, TTRSS::Class, "/"],
            [new ServerRequest([], [], "/no/such/api/", "HEAD"),                       "GET",  false],
            [new ServerRequest([], [], "/no/such/api/", "GET"),                        "GET",  false],
        ];
    }
}