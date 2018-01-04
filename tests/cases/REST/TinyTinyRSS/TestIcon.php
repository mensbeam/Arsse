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
use Zend\Diactoros\Response\EmptyResponse as Response;
use Phake;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Icon<extended> */
class TestIcon extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;

    public function setUp() {
        $this->clearData();
        Arsse::$conf = new Conf();
        // create a mock user manager
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        $this->h = new Icon();
    }

    public function tearDown() {
        $this->clearData();
    }

    public function testRetrieveFavion() {
        Phake::when(Arsse::$db)->subscriptionFavicon->thenReturn("");
        Phake::when(Arsse::$db)->subscriptionFavicon(42)->thenReturn("http://example.com/favicon.ico");
        Phake::when(Arsse::$db)->subscriptionFavicon(2112)->thenReturn("http://example.net/logo.png");
        Phake::when(Arsse::$db)->subscriptionFavicon(1337)->thenReturn("http://example.org/icon.gif\r\nLocation: http://bad.example.com/");
        // these requests should succeed
        $exp = new Response(301, ['Location' => "http://example.com/favicon.ico"]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "42.ico")));
        $exp = new Response(301, ['Location' => "http://example.net/logo.png"]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "2112.ico")));
        $exp = new Response(301, ['Location' => "http://example.org/icon.gif"]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "1337.ico")));
        // these requests should fail
        $exp = new Response(404);
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "ook.ico")));
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "ook")));
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "47.ico")));
        $this->assertResponse($exp, $this->h->dispatch(new Request("GET", "2112.png")));
        // only GET is allowed
        $exp = new Response(405, ['Allow' => "GET"]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("PUT", "2112.ico")));
    }
}
