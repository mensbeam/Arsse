<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Auth extends \JKingWeb\Arsse\REST\AbstractHandler {
    public function __construct() {
    }

    /** Authenticates a user and creates a relatively long-lived session token
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/terminology.html#authentication
     */
    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $target = $req->getRequestTarget();
        // ensure the URL is correct; the full path is already stripped by the global handler, so we should have no path remaining
        if ((parse_url($target, \PHP_URL_PATH) ?? "") !== "") {
            return HTTP::respEmpty(404);
        } elseif (!in_array($req->getMethod(), ["GET", "POST"])) {
            return HTTP::respEmpty(405);
        }
        // issue an HTTP Basic authentication challenge if we require it (this is our own extension)
        if ($this->shouldChallenge($req)) {
            return HTTP::respEmpty(401);
        }
        // get the login data, preferring GET data; which FreshRSS prefers
        //   depends on PHP configuration, for which the default is GET first
        parse_str((string) $req->getBody(), $body);
        parse_str((string) parse_url($target, \PHP_URL_QUERY), $query);
        $user = $query['Email'] ?? $body['Email'] ?? "";
        $pass = $query['Passwd'] ?? $body['Passwd'] ?? "";
        // attempt to authenticate the user
        if (Arsse::$user->auth($user, $pass)) {
            // successful authentication creates a long-lived token which can be used to authenticate other requests
            $token = Arsse::$db->tokenCreate($user, "reader.login", null, $this->now()->add(new \DateInterval("P7D")));
            return HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\n");
        } else {
            // NOTE: FreshRSS uses a different error response, but this seems
            //   to be what all other Reader implementations do. Additionally,
            //   at least some clients (e.g. Newsflash) treat a 401 response
            //   as a request for HTTP Basic authentication, so we respond
            //   with 400 instead
            return HTTP::respText("Error=BadAuthentication\n", 400);
        }
    }
}