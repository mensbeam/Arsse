<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\REST\Response;

class Icon extends \JKingWeb\Arsse\REST\AbstractHandler {

    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if ($req->method != "GET") {
            // only GET requests are allowed
            return new Response(405, "", "", ["Allow: GET"]);
        } elseif (!preg_match("<^(\d+)\.ico$>", $req->url, $match) || !((int) $match[1])) {
            return new Response(404);
        }
        $url = Arsse::$db->subscriptionFavicon((int) $match[1]);
        if ($url) {
            // strip out anything after literal line-end characters; this is to mitigate a potential header (e.g. cookie) injection from the URL
            if (($pos = strpos($url, "\r")) !== FALSE || ($pos = strpos($url, "\n")) !== FALSE) {
                $url = substr($url, 0, $pos);
            }
            return new Response(301, "", "", ["Location: $url"]);
        } else {
            return new Response(404);
        }
    }
}