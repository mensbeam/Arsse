<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\REST\Response;

class Versions extends \JKingWeb\Arsse\REST\AbstractHandler {
	function __construct() {
	}

	function dispatch(\JKingWeb\Arsse\REST\Request $req): \JKingWeb\Arsse\REST\Response {
		// parse the URL and populate $path and $query
		extract($this->parseURL($req->url));
		// if a method other than GET was used, this is an error
		if($req->method != "GET") {
			return new Response(405);
		}
		if(preg_match("<^/?$>",$path)) {
			// if the request path is an empty string or just a slash, return the supported versions
			$out = [
				'apiLevels' => [
					'v1-2',
				]
			];
			return new Response(200, $out);
		} else {
			// if the URL path was anything else, the client is probably trying a version we don't support
			return new Response(404);
		}
	}
}