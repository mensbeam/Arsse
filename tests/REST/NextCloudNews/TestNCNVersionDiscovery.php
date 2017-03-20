<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use JKingWeb\NewsSync\Rest\Request;
use JKingWeb\NewsSync\Rest\Response;


class TestNCNVersionDiscovery extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

	function setUp() {
		$conf = new Conf();
		$this->data = new Test\RuntimeData($conf);
	}

	function testVersionList() {
		$exp = new Response(200, ['apiLevels' => ['v1-2']]);
		$req = new Request("GET", "/");
		$h = new Rest\NextCloudNews\Versions($this->data);
		$res = $h->dispatch($req);
		$this->assertEquals($exp, $res);
	}
}