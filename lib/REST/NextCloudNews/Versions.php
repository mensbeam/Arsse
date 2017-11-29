<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;

use JKingWeb\Arsse\REST\Response;

class Versions implements \JKingWeb\Arsse\REST\Handler {
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if (!preg_match("<^/?$>", $req->path)) {
            // if the request path is an empty string or just a slash, the client is probably trying a version we don't support
            return new Response(404);
        } elseif ($req->method=="OPTIONS") {
            // if the request method is OPTIONS, respond accordingly
            return new Response(204, "", "", ["Allow: HEAD,GET"]);
        } elseif ($req->method != "GET") {
            // if a method other than GET was used, this is an error
            return new Response(405, "", "", ["Allow: HEAD,GET"]);
        } else {
            // otherwise return the supported versions
            $out = [
                'apiLevels' => [
                    'v1-2',
                ]
            ];
            return new Response(200, $out);
        }
    }
}
