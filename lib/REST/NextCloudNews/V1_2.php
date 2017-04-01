<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\REST\Response;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
	function __construct() {
	}

	function dispatch(\JKingWeb\Arsse\REST\Request $req): \JKingWeb\Arsse\REST\Response {
		// parse the URL and populate $path and $query
		extract($this->parseURL($req->url));
		if(preg_match("<^/(items|folders|feeds|cleanup|version|status|user)(?:/([^/]+))?(?:/([^/]+))?(?:/([^/]+))?/?$>", $path, $matches)) {
			$scope = $matches[1];
			var_export($scope);
		} else {
			return new Response(404);
		}
	}
}