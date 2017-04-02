<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\REST\Response;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
	function __construct() {
	}

	function dispatch(\JKingWeb\Arsse\REST\Request $req): \JKingWeb\Arsse\REST\Response {
		// try to authenticate
		if(!Data::$user->authHTTP()) return new Response(401, "", null, ['WWW-Authenticate: Basic realm="NextCloud News API"']);
		// only accept GET, POST, or PUT
		if(!in_array($req->method, ["GET", "POST", "PUT"])) return new Response(405);
		// match the path
		if(preg_match("<^/(items|folders|feeds|cleanup|version|status|user)(?:/([^/]+))?(?:/([^/]+))?(?:/([^/]+))?/?$>", $req->path, $match)) {
			// dispatch
			switch($match[1]) {
				case "folders":
					switch($req->method) {
						case "GET":  return $this->folderList();
						case "POST": return $this->folderAdd($this->normalizeInput($req));
						case "PUT":
							list($path, $scope, $id, $action) = $match;
							return $this->folderEdit($id, $action, $this->normalizeInput($req));
					}
				default: return new Response(404);
			}
		} else {
			return new Response(404);
		}
	}

	protected function folderList(): Response {
		$folders = Data::$db->folderList(Data::$user->id, null, false)->getAll();
		return new Response(200, ['folders' => $folders]);
	}
}