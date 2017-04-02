<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\Rest\Request;
use JKingWeb\Arsse\Rest\Response;
use JKingWeb\Arsse\Test\Result;
use Phake;


class TestNCNV1_2 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

	protected $h;

	function setUp() {
		$this->clearData();
        // create a mock user manager
        Data::$user = Phake::mock(User::class);
        Phake::when(Data::$user)->authHTTP->thenReturn(true); 
		Data::$user->id = "john.doe@example.com";
		// create a mock database interface
		Data::$db = Phake::mock(Database::Class);
		$this->h = new REST\NextCloudNews\V1_2();
	}

	function tearDown() {
		$this->clearData();
	}

	function testListFolders() {
		$list = [
			['id' => 1,  'name' => "Software", 'parent' => null],
			['id' => 12, 'name' => "Hardware", 'parent' => null],
		];
		Phake::when(Data::$db)->folderList(Data::$user->id, null, false)->thenReturn(new Result($list));
		$exp = new Response(200, ['folders' => $list]);
		$this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/folders")));
	}
}