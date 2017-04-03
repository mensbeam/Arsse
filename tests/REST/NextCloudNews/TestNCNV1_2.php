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

	function testRespondToInvalidPaths() {
		$errs = [
			404 => [
				['GET',    "/"],
				['PUT',    "/"],
				['POST',   "/"],
				['DELETE', "/"],
				['GET',    "/folders/1/invalid"],
				['PUT',    "/folders/1/invalid"],
				['POST',   "/folders/1/invalid"],
				['DELETE', "/folders/1/invalid"],
			],
			405 => [
				'GET, POST' => [
					['PUT',    "/folders"],
					['DELETE', "/folders"],
				],
				'PUT, DELETE' => [
					['GET',    "/folders/1"],
					['POST',   "/folders/1"],
				],
			],
		];
		foreach($errs[404] as $req) {
			$exp = new Response(404);
			list($method, $path) = $req;
			$this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 404.");
		}
		foreach($errs[405] as $allow => $cases) {
			$exp = new Response(405, "", "", ['Allow: '.$allow]);
			foreach($cases as $req) {
				list($method, $path) = $req;
				$this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 405.");
			}
		}
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
		// set of various mocks for testing
		Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[0])->thenReturn(1)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
		Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[1])->thenReturn(2)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
		Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 1)->thenReturn($out[0]);
		Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 2)->thenReturn($out[1]);
		// set up mocks that produce errors
		Phake::when(Data::$db)->folderAdd(Data::$user->id, [])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
		Phake::when(Data::$db)->folderAdd(Data::$user->id, ['name' => ""])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
		Phake::when(Data::$db)->folderAdd(Data::$user->id, ['name' => " "])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("whitespace"));
		// correctly add two folders, using different means
		$exp = new Response(200, ['folders' => [$out[0]]]);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[0]), 'application/json')));
		$exp = new Response(200, ['folders' => [$out[1]]]);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Hardware")));
		Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[0]);
		Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[1]);
		Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 1);
		Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 2);
		// test bad folder names
		$exp = new Response(422);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders")));
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":""}', 'application/json')));
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":" "}', 'application/json')));
		// try adding the same two folders again
		$exp = new Response(409);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Software")));
		$exp = new Response(409);
		$this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[1]), 'application/json')));
	}

	function testRemoveAFolder() {
		Phake::when(Data::$db)->folderRemove(Data::$user->id, 1)->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("idMissing"));
		$exp = new Response(204);
		$this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
		// fail on the second invocation because it no longer exists
		$exp = new Response(404);
		$this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
		Phake::verify(Data::$db, Phake::times(2))->folderRemove(Data::$user->id, 1);
		// use a non-integer folder ID
		$exp = new Response(404);
		$this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/invalid")));
	}

	function testRenameAFolder() {
		$in = [
			["name" => "Software"],
			["name" => "Software"],
			["name" => ""],
			["name" => " "],
			[],
		];
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[0])->thenReturn(true);
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 2, $in[1])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation"));
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[2])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[3])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("whitespace"));
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[4])->thenReturn(true); // this should be stopped by the handler before the request gets to the database
		Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 3, $this->anything())->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("idMissing")); // folder ID 3 does not exist
		$exp = new Response(204);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[0]), 'application/json')));
		$exp = new Response(409);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/2", json_encode($in[1]), 'application/json')));
		$exp = new Response(422);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[2]), 'application/json')));
		$exp = new Response(422);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[3]), 'application/json')));
		$exp = new Response(422);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[4]), 'application/json')));
		$exp = new Response(404);
		$this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/3", json_encode($in[0]), 'application/json')));
	}
}