<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\TinyTinyRSS\Icon;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse as Response;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Icon<extended> */
class TestIcon extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $user = "john.doe@example.com";

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        $this->h = new Icon();
    }

    public function tearDown(): void {
        self::clearData();
    }

    protected function req(string $target, string $method = "GET", string $user = null): ResponseInterface {
        $prefix = "/tt-rss/feed-icons/";
        $url = $prefix.$target;
        $req = $this->serverRequest($method, $url, $prefix, [], [], null, "", [], $user);
        return $this->h->dispatch($req);
    }

    protected function reqAuth(string $target, string $method = "GET"): ResponseInterface {
        return $this->req($target, $method, $this->user);
    }

    protected function reqAuthFailed(string $target, string $method = "GET"): ResponseInterface {
        return $this->req($target, $method, "");
    }

    public function testRetrieveFavion(): void {
        \Phake::when(Arsse::$db)->subscriptionIcon->thenReturn(['url' => null]);
        \Phake::when(Arsse::$db)->subscriptionIcon($this->anything(), 1123, false)->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionIcon($this->anything(), 42, false)->thenReturn(['url' => "http://example.com/favicon.ico"]);
        \Phake::when(Arsse::$db)->subscriptionIcon($this->anything(), 2112, false)->thenReturn(['url' => "http://example.net/logo.png"]);
        \Phake::when(Arsse::$db)->subscriptionIcon($this->anything(), 1337, false)->thenReturn(['url' => "http://example.org/icon.gif\r\nLocation: http://bad.example.com/"]);
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
        $this->assertMessage($exp, $this->req("1123.ico"));
        // only GET is allowed
        $exp = new Response(405, ['Allow' => "GET"]);
        $this->assertMessage($exp, $this->req("2112.ico", "PUT"));
    }

    public function testRetrieveFavionWithHttpAuthentication(): void {
        $url = ['url' => "http://example.org/icon.gif\r\nLocation: http://bad.example.com/"];
        \Phake::when(Arsse::$db)->subscriptionIcon->thenReturn(['url' => null]);
        \Phake::when(Arsse::$db)->subscriptionIcon($this->user, 42, false)->thenReturn($url);
        \Phake::when(Arsse::$db)->subscriptionIcon("jane.doe", 2112, false)->thenReturn($url);
        \Phake::when(Arsse::$db)->subscriptionIcon($this->user, 1337, false)->thenReturn($url);
        \Phake::when(Arsse::$db)->subscriptionIcon(null, 42, false)->thenReturn($url);
        \Phake::when(Arsse::$db)->subscriptionIcon(null, 2112, false)->thenReturn($url);
        \Phake::when(Arsse::$db)->subscriptionIcon(null, 1337, false)->thenReturn($url);
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
        self::setConf(['userHTTPAuthRequired' => true]);
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
