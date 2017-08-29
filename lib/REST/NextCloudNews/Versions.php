<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;

use JKingWeb\Arsse\REST\Response;

class Versions implements \JKingWeb\Arsse\REST\Handler {
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        // if a method other than GET was used, this is an error
        if ($req->method != "GET") {
            return new Response(405);
        }
        if (preg_match("<^/?$>", $req->path)) {
            // if the request path is an empty string or just a slash, return the supported versions
            $out = [
                'apiLevels' => [
                    'v1-2',
                ]
            ];
            return new Response(200, $out);
        } else {
            // if the URL path was anything else, the client is probably trying a version we don't support
            return new Response(501);
        }
    }
}
