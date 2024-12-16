<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\TinyTinyRSS\Icon;
use Psr\Http\Message\ResponseInterface;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Icon<extended> */
class TestIcon extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $user = "john.doe@example.com";

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        Arsse::$user = $this->mock(User::class)->get();
        // create a mock database interface
        $this->dbMock = $this->mock(Database::class);
        $this->h = new Icon();
    }

    protected function req(string $target, string $method = "GET", ?string $user = null): ResponseInterface {
        Arsse::$db = $this->dbMock->get();
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
        $this->dbMock->subscriptionIcon->returns(['url' => null]);
        $this->dbMock->subscriptionIcon->with($this->anything(), 1123, false)->throws(new ExceptionInput("subjectMissing"));
        $this->dbMock->subscriptionIcon->with($this->anything(), 42, false)->returns(['url' => "http://example.com/favicon.ico"]);
        $this->dbMock->subscriptionIcon->with($this->anything(), 2112, false)->returns(['url' => "http://example.net/logo.png"]);
        $this->dbMock->subscriptionIcon->with($this->anything(), 1337, false)->returns(['url' => "http://example.org/icon.gif\r\nLocation: http://bad.example.com/"]);
        // these requests should succeed
        $exp = HTTP::respEmpty(301, ['Location' => "http://example.com/favicon.ico"]);
        $this->assertMessage($exp, $this->req("42.ico"));
        $exp = HTTP::respEmpty(301, ['Location' => "http://example.net/logo.png"]);
        $this->assertMessage($exp, $this->req("2112.ico"));
        $exp = HTTP::respEmpty(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->req("1337.ico"));
        // these requests should fail
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("ook.ico"));
        $this->assertMessage($exp, $this->req("ook"));
        $this->assertMessage($exp, $this->req("47.ico"));
        $this->assertMessage($exp, $this->req("2112.png"));
        $this->assertMessage($exp, $this->req("1123.ico"));
        // only GET is allowed
        $exp = HTTP::respEmpty(405, ['Allow' => "GET"]);
        $this->assertMessage($exp, $this->req("2112.ico", "PUT"));
    }

    public function testRetrieveFavionWithHttpAuthentication(): void {
        $url = ['url' => "http://example.org/icon.gif\r\nLocation: http://bad.example.com/"];
        $this->dbMock->subscriptionIcon->returns(['url' => null]);
        $this->dbMock->subscriptionIcon->with($this->user, 42, false)->returns($url);
        $this->dbMock->subscriptionIcon->with("jane.doe", 2112, false)->returns($url);
        $this->dbMock->subscriptionIcon->with($this->user, 1337, false)->returns($url);
        $this->dbMock->subscriptionIcon->with(null, 42, false)->returns($url);
        $this->dbMock->subscriptionIcon->with(null, 2112, false)->returns($url);
        $this->dbMock->subscriptionIcon->with(null, 1337, false)->returns($url);
        // these requests should succeed
        $exp = HTTP::respEmpty(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->req("42.ico"));
        $this->assertMessage($exp, $this->req("2112.ico"));
        $this->assertMessage($exp, $this->req("1337.ico"));
        $this->assertMessage($exp, $this->reqAuth("42.ico"));
        $this->assertMessage($exp, $this->reqAuth("1337.ico"));
        // these requests should fail
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->reqAuth("2112.ico"));
        $exp = HTTP::respEmpty(401);
        $this->assertMessage($exp, $this->reqAuthFailed("42.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("1337.ico"));
        // with HTTP auth required, only authenticated requests should succeed
        self::setConf(['userHTTPAuthRequired' => true]);
        $exp = HTTP::respEmpty(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertMessage($exp, $this->reqAuth("42.ico"));
        $this->assertMessage($exp, $this->reqAuth("1337.ico"));
        // anything else should fail
        $exp = HTTP::respEmpty(401);
        $this->assertMessage($exp, $this->req("42.ico"));
        $this->assertMessage($exp, $this->req("2112.ico"));
        $this->assertMessage($exp, $this->req("1337.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("42.ico"));
        $this->assertMessage($exp, $this->reqAuthFailed("1337.ico"));
        // resources for the wrtong user should still fail, too
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->reqAuth("2112.ico"));
    }
}
