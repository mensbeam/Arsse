<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\REST\NextCloudNews;
use JKingWeb\NewsSync\REST\Response;

class Versions implements \JKingWeb\NewsSync\REST\Handler {
	function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
	}

	function dispatch(\JKingWeb\NewsSync\REST\Request $req): \JKingWeb\NewsSync\REST\Response {
		$path = $req->url;
		$query = "";
		if(strpos($path, "?") !== false) {
			list($path, $query) = explode("?", $path);
		}
		if($req->method != "GET") {
			return new Response(405);
		}
		if(preg_match("<^/?$>",$path)) {
			$out = [
				'apiLevels' => [
					'v1-2'
				]
			];
			return new Response(200, $out);
		} else {
			return new Response(404);
		}
	}
}