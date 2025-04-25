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
    use Common;

    public function __construct() {
    }

    /** Authenticates a user and creates a relatively long-lived session token
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/terminology.html#authentication
     */
    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $target = $req->getRequestTarget();
        // ensure the URL is correct; the full path is already stripped by the global handler, so we should have no path remaining
        if (parse_url($target, \PHP_URL_PATH) !== "") {
            return HTTP::respEmpty(404);
        }
        // get the login data, preferring POST data
        parse_str((string) $req->getBody(), $data);
        if (!$data) {
            parse_str(parse_url($target, \PHP_URL_QUERY), $data);
        }
        // attempt to authenticate the user
        $user = $data['Email'] ?? "";
        $pass = $data['Passwd'] ?? "";
        if (Arsse::$user->auth($user, $pass)) {
            // successful authentication creates a long-lived token which can be used to authenticate other requests
            $token = Arsse::$db->tokenCreate($user, "reader.login", null, $this->now()->add(new \DateInterval("P7D")));
            return HTTP::respText("SID=$token\nLSID=$token\nAuth=$token");
        } else {
            // NOTE: FreshRSS uses a different error response, but this seems to be what all other Reader implementations do
            return $this->challenge(HTTP::respText("Error=BadAuthentication", 401));
        }
    }
}