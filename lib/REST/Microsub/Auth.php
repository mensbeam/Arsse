<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Microsub;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\URL;
use JKingWeb\Arsse\Misc\Date;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\HtmlResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;

class Auth extends \JKingWeb\Arsse\REST\AbstractHandler {
    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // ensure that a user name is specified in the path
        // if the path is empty or contains a slash, this is not a URL we handle
        $id = parse_url($req->getRequestTarget())['path'] ?? "";
        if (!strlen($id) || strpos($id, "/") !== false) {
            return new EmptyResponse(404);
        }
        $id = rawurldecode($id);
        // gather the query parameters and act on the "proc" parameter
        $method = "do".ucfirst(strtolower($req->getQueryParams()['proc'] ?? "discovery"));
        if (!method_exists($this, $method)) {
            return new EmptyResponse(404);
        } else {
            return $this->$method($id, $req);
        }
    }

    protected function buildIdentifier(ServerRequestInterface $req, bool $baseOnly = false): string {
        // construct the base user identifier URL; the user is never checked against the database
        $s = $req->getServerParams();
        $path = $req->getRequestTarget()['path'];
        $https = (strlen($s['HTTPS'] ?? "") && $s['HTTPS'] !== "off");
        $port = (int) $s['SERVER_PORT'];
        $port = (!$port || ($https && $port == 443) || (!$https && $port == 80)) ? "" : ":$port";
        $base = URL::normalize(($https ? "https" : "http")."://".$s['HTTP_HOST'].$port."/");
        return !$baseOnly ? URL::normalize($base.$path) : $base;
    }

    protected function doDiscovery(string $user, ServerRequestInterface $req): ResponseInterface {
        // as this route is publicly accessible, for reasons of privacy requests for user discovery work regardless of whether the user exists
        // prepare authroizer, token, and Microsub endpoint URLs
        $base = $this->buildIdentifier($req, true);
        $id = $this->buildIdentifier($req);
        $urlAuth = $id."?proc=login";
        $urlToken = $id."?proc=issue";
        $urlService = $base."microsub";
        // output an extremely basic identity resource
        $html = '<meta charset="UTF-8"><link rel="authorization_endpoint" href="'.htmlspecialchars($urlAuth).'"><link rel="token_endpoint" href="'.htmlspecialchars($urlToken).'"><link rel="microsub" href="'.htmlspecialchars($urlService).'">';
        return new Response($html, 200, [
            "Link: <$urlAuth>; rel=\"authorization_endpoint\"",
            "Link: <$urlToken>; rel=\"token_endpoint\"",
            "Link: <$urlService>; rel=\"microsub\"",
        ]);
    }

    protected function doLogin(string $user, ServerRequestInterface $req): ResponseInterface {
        if (!$req->getAttribute("authenticated", false)) {
            // user has not yet logged in, or has failed to log in
            return new EmptyResponse(401);
        } else {
            // user has logged in
            // ensure the logged-in user matches the IndieAuth identifier URL
            $id = $req->getAttribute("authenticatedUser");
            $query = $req->getQueryParams();
            $url = buildIdentifier($req);
            if ($user !== $id || URL::normalize($query['me']) !== $url) {
                return new EmptyResponse(403);
            } else {
                $redir = URL::normalize(rawurldecode($query['redirect_uri']));
                $state = $query['state'] ?? "";
                // check that the redirect URL is an absolute one
                if (!URL::absolute($redir)) {
                    return new EmptyResponse(400);
                }
                // issue an authorization code and build the redirect URL
                $code = Arsse::$db->tokenCreate($id, "microsub.auth", null, Date::add("PT2M"));
                $next = URL::queryAppend($redir, "code=$code&state=$state");
                return new EmptyResponse(302, ["Location: $next"]);
            }
        }
    }
}
