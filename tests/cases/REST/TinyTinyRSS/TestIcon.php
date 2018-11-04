<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\REST\TinyTinyRSS\Icon;
use JKingWeb\Arsse\REST\Request;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\EmptyResponse as Response;
use Phake;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Icon<extended> */
class TestIcon extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $user = "john.doe@example.com";

    public function setUp() {
        $this->clearData();
        $this->setConf();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        $this->h = new Icon();
    }

    public function tearDown() {
        $this->clearData();
    }

    protected function req(string $target, string $method = "GET", string $user = null): ResponseInterface {
        $url = "/tt-rss/feed-icons/".$target;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
        ];
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        $req = $req->withRequestTarget($target);
        if (isset($user)) {
            if (strlen($user)) {
                $req = $req->withAttribute("authenticated", true)->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        return $this->h->dispatch($req);
    }

    protected function reqAuth(string $target, string $method = "GET") {
        return $this->req($target, $method, $this->user);
    }

    protected function reqAuthFailed(string $target, string $method = "GET") {
        return $this->req($target, $method, "");
    }

    public function testRetrieveFavion() {
        Phake::when(Arsse::$db)->subscriptionFavicon->thenReturn("");
        Phake::when(Arsse::$db)->subscriptionFavicon(42, $this->anything())->thenReturn("http://example.com/favicon.ico");
        Phake::when(Arsse::$db)->subscriptionFavicon(2112, $this->anything())->thenReturn("http://example.net/logo.png");
        Phake::when(Arsse::$db)->subscriptionFavicon(1337, $this->anything())->thenReturn("http://example.org/icon.gif\r\nLocation: http://bad.example.com/");
        // these requests should succeed
        $exp = new Response(301, ['Location' => "http://example.com/favicon.ico"]);
        $this->assertMessage($exp, $this->req("42.ico"));
        $exp = new Response(301, ['Location' => "http://example.net/logo.png"]);
        $this->assertMessage($exp, $this->req("2112.ico"));
        $exp = new Response(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->req("1337.ico"));
        // these requests should fail
        $exp = new Response(404);
        $this->assertMessage($exp, $this->req("ook.ico"));
        $this->assertMessage($exp, $this->req("ook"));
        $this->assertMessage($exp, $this->req("47.ico"));
        $this->assertMessage($exp, $this->req("2112.png"));
        // only GET is allowed
        $exp = new Response(405, ['Allow' => "GET"]);
        $this->assertMessage($exp, $this->req("2112.ico", "PUT"));
    }

    public function testRetrieveFavionWithHttpAuthentication() {
        $url = "http://example.org/icon.gif\r\nLocation: http://bad.example.com/";
        Phake::when(Arsse::$db)->subscriptionFavicon->thenReturn("");
        Phake::when(Arsse::$db)->subscriptionFavicon(42, $this->user)->thenReturn($url);
        Phake::when(Arsse::$db)->subscriptionFavicon(2112, "jane.doe")->thenReturn($url);
        Phake::when(Arsse::$db)->subscriptionFavicon(1337, $this->user)->thenReturn($url);
        Phake::when(Arsse::$db)->subscriptionFavicon(42, null)->thenReturn($url);
        Phake::when(Arsse::$db)->subscriptionFavicon(2112, null)->thenReturn($url);
        Phake::when(Arsse::$db)->subscriptionFavicon(1337, null)->thenReturn($url);
        // these requests should succeed
        $exp = new Response(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->req("42.ico"));
        $this->assertMessage($exp, $this->req("2112.ico"));
        $this->assertMessage($exp, $this->req("1337.ico"));
        $this->assertMessage($exp, $this->reqAuth("42.ico"));
        $this->assertMessage($exp, $this->reqAuth("1337.ico"));
        // these requests should fail
        $exp = new Response(404);
        $this->assertMessage($exp, $this->reqAuth("2112.ico"));
        $exp = new Response(401);
        $this->assertMessage($exp, $this->reqAuthFailed("42.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("1337.ico"));
        // with HTTP auth required, only authenticated requests should succeed
        $this->setConf(['userHTTPAuthRequired' => true]);
        $exp = new Response(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->reqAuth("42.ico"));
        $this->assertMessage($exp, $this->reqAuth("1337.ico"));
        // anything else should fail
        $exp = new Response(401);
        $this->assertMessage($exp, $this->req("42.ico"));
        $this->assertMessage($exp, $this->req("2112.ico"));
        $this->assertMessage($exp, $this->req("1337.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("42.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("1337.ico"));
        // resources for the wrtong user should still fail, too
        $exp = new Response(404);
        $this->assertMessage($exp, $this->reqAuth("2112.ico"));
    }
}
