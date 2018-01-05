<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextCloudNews;

use JKingWeb\Arsse\REST\NextCloudNews\Versions;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\Versions */
class TestVersions extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        $this->clearData();
    }

    protected function req(string $method, string $target): ResponseInterface {
        $url = "/index.php/apps/news/api".$target;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
        ];
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        $req = $req->withRequestTarget($target);
        return (new Versions)->dispatch($req);
    }

    public function testFetchVersionList() {
        $exp = new Response(['apiLevels' => ['v1-2']]);
        $this->assertResponse($exp, $this->req("GET", "/"));
        $this->assertResponse($exp, $this->req("GET", "/"));
        $this->assertResponse($exp, $this->req("GET", "/"));
    }

    public function testRespondToOptionsRequest() {
        $exp = new EmptyResponse(204, ['Allow' => "HEAD,GET"]);
        $this->assertResponse($exp, $this->req("OPTIONS", "/"));
    }

    public function testUseIncorrectMethod() {
        $exp = new EmptyResponse(405, ['Allow' => "HEAD,GET"]);
        $this->assertResponse($exp, $this->req("POST", "/"));
    }

    public function testUseIncorrectPath() {
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("GET", "/ook"));
        $this->assertResponse($exp, $this->req("OPTIONS", "/ook"));
    }
}
