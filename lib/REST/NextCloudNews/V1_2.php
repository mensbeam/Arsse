<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\REST\Response;
use JKingWeb\Arsse\AbstractException;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
	function __construct() {
	}

	function dispatch(\JKingWeb\Arsse\REST\Request $req): \JKingWeb\Arsse\REST\Response {
		// try to authenticate
		if(!Data::$user->authHTTP()) return new Response(401, "", "", ['WWW-Authenticate: Basic realm="NextCloud News API"']);
		// only accept GET, POST, or PUT
		if(!in_array($req->method, ["GET", "POST", "PUT"])) return new Response(405, "", "", ['Allow: GET, POST, PUT']);
		// normalize the input
		if($req->body) {
			// if the entity body is not JSON according to content type, return "415 Unsupported Media Type"
			if(!preg_match("<^application/json\b|^$>", $req->type)) return new Response(415, "", "", ['Accept: application/json']);
			try {
				$data = json_decode($req->body, true);
			} catch(\Throwable $e) {
				// if the body could not be parsed as JSON, return "400 Bad Request"
				return new Response(400);
			}
		} else {
			$data = [];
		}
		// FIXME: Do query parameters take precedence in NextCloud? Is there a conflict error when values differ?
		$data = array_merge($data, $req->query);		
		// match the path
		if(preg_match("<^/(items|folders|feeds|cleanup|version|status|user)(?:/([^/]+))?(?:/([^/]+))?(?:/([^/]+))?/?$>", $req->path, $match)) {
			// dispatch
			try {
				switch($match[1]) {
					case "folders":
						switch($req->method) {
							case "GET":  return $this->folderList();
							case "POST": return $this->folderAdd($data);
							case "PUT":
								return $this->folderEdit($match[2], $data);
						}
					default: return new Response(404);
				}
			} catch(Exception $e) {
				// if there was a REST exception return 400
				return new Response(400);
			} catch(AbstractException $e) {
				// if there was any other Arsse exception return 500
				return new Response(500);
			}
		} else {
			return new Response(404);
		}
	}

	protected function folderList(): Response {
		$folders = Data::$db->folderList(Data::$user->id, null, false)->getAll();
		return new Response(200, ['folders' => $folders]);
	}

	protected function folderAdd($data): Response {
		try {
			$folder = Data::$db->folderAdd(Data::$user->id, $data);
		} catch(\JKingWeb\Arsse\Db\ExceptionInput $e) {
			switch($e->getCode()) {
				// folder already exists
				case 10236: return new Response(409);
				// folder name not acceptable
				case 10231:
				case 10232: return new Response(422);
				// other errors related to input
				default: return new Response(400);
			}
		}
		$folder = Data::$db->folderPropertiesGet(Data::$user->id, $folder);
		return new Response(200, ['folders' => [$folder]]);
	}
}