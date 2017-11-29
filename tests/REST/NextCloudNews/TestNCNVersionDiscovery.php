<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\REST\Response;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\Versions */
class TestNCNVersionDiscovery extends Test\AbstractTest {
    public function setUp() {
        $this->clearData();
    }

    public function testFetchVersionList() {
        $exp = new Response(200, ['apiLevels' => ['v1-2']]);
        $h = new REST\NextCloudNews\Versions();
        $req = new Request("GET", "/");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
        $req = new Request("GET", "");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
        $req = new Request("GET", "/?id=1827");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
    }

    public function testRespondToOptionsRequest() {
        $exp = new Response(204, "", "", ["Allow: HEAD,GET"]);
        $h = new REST\NextCloudNews\Versions();
        $req = new Request("OPTIONS", "/");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
    }

    public function testUseIncorrectMethod() {
        $exp = new Response(405, "", "", ["Allow: HEAD,GET"]);
        $h = new REST\NextCloudNews\Versions();
        $req = new Request("POST", "/");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
    }

    public function testUseIncorrectPath() {
        $exp = new Response(404);
        $h = new REST\NextCloudNews\Versions();
        $req = new Request("GET", "/ook");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
        $req = new Request("OPTIONS", "/ook");
        $res = $h->dispatch($req);
        $this->assertEquals($exp, $res);
    }
}
