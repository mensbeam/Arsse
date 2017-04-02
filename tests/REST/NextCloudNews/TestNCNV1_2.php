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

	function testAddAFolder() {
		$in = [
			["name" => "Software"],
			["name" => "Hardware"],
		];
		$out = [
			['id' => 1, 'name' => "Software", 'parent' => null],
			['id' => 2, 'name' => "Hardware", 'parent' => null],
		];
		Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[0])->thenReturn(1);
		Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[1])->thenReturn(2);
		Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 1)->thenReturn($out[0]);
		Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 2)->thenReturn($out[1]);
		$exp = new Response(200, ['folders' => [$out[0]]]);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[0]), 'application/json')));
		$exp = new Response(200, ['folders' => [$out[1]]]);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Hardware")));
		Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[0]);
		Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[1]);
		Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 1);
		Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 2);
	}
}