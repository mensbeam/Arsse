<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews;

use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\NextcloudNews\Versions;
use Psr\Http\Message\ResponseInterface;

/** @covers \JKingWeb\Arsse\REST\NextcloudNews\Versions */
class TestVersions extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        parent::setUp();
    }

    protected function req(string $method, string $target): ResponseInterface {
        $prefix = "/index.php/apps/news/api";
        $url = $prefix.$target;
        $req = $this->serverRequest($method, $url, $prefix);
        return (new Versions)->dispatch($req);
    }

    public function testFetchVersionList(): void {
        $exp = HTTP::respJson(['apiLevels' => ['v1-2']]);
        $this->assertMessage($exp, $this->req("GET", "/"));
        $this->assertMessage($exp, $this->req("GET", "/"));
        $this->assertMessage($exp, $this->req("GET", "/"));
    }

    public function testRespondToOptionsRequest(): void {
        $exp = HTTP::respEmpty(204, ['Allow' => "HEAD,GET"]);
        $this->assertMessage($exp, $this->req("OPTIONS", "/"));
    }

    public function testUseIncorrectMethod(): void {
        $exp = HTTP::respEmpty(405, ['Allow' => "HEAD,GET"]);
        $this->assertMessage($exp, $this->req("POST", "/"));
    }

    public function testUseIncorrectPath(): void {
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("GET", "/ook"));
        $this->assertMessage($exp, $this->req("OPTIONS", "/ook"));
    }
}
