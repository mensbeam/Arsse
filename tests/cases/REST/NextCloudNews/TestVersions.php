<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextCloudNews;

use JKingWeb\Arsse\REST\NextCloudNews\Versions;
use JKingWeb\Arsse\REST\Request;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\Versions */
class TestVersions extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        $this->clearData();
    }

    public function testFetchVersionList() {
        $exp = new Response(['apiLevels' => ['v1-2']]);
        $h = new Versions;
        $req = new Request("GET", "/");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
        $req = new Request("GET", "");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
        $req = new Request("GET", "/?id=1827");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
    }

    public function testRespondToOptionsRequest() {
        $exp = new EmptyResponse(204, ['Allow' => "HEAD,GET"]);
        $h = new Versions;
        $req = new Request("OPTIONS", "/");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
    }

    public function testUseIncorrectMethod() {
        $exp = new EmptyResponse(405, ['Allow' => "HEAD,GET"]);
        $h = new Versions;
        $req = new Request("POST", "/");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
    }

    public function testUseIncorrectPath() {
        $exp = new EmptyResponse(404);
        $h = new Versions;
        $req = new Request("GET", "/ook");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
        $req = new Request("OPTIONS", "/ook");
        $res = $h->dispatch($req);
        $this->assertResponse($exp, $res);
    }
}
