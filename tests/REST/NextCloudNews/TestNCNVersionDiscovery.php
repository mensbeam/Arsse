<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\Rest\Request;
use JKingWeb\Arsse\Rest\Response;


class TestNCNVersionDiscovery extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

	function setUp() {
		$conf = new Conf();
		$this->data = new Test\RuntimeData($conf);
	}

	function testFetchVersionList() {
		$exp = new Response(200, ['apiLevels' => ['v1-2']]);
		$h = new Rest\NextCloudNews\Versions($this->data);
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

	function testUseIncorrectMethod() {
		$exp = new Response(405);
		$h = new Rest\NextCloudNews\Versions($this->data);
		$req = new Request("POST", "/");
		$res = $h->dispatch($req);
		$this->assertEquals($exp, $res);
	}

	function testUseIncorrectPath() {
		$exp = new Response(404);
		$h = new Rest\NextCloudNews\Versions($this->data);
		$req = new Request("GET", "/ook");
		$res = $h->dispatch($req);
		$this->assertEquals($exp, $res);
	}
}