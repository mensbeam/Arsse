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
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

class Auth extends \JKingWeb\Arsse\REST\AbstractHandler {
    /** The scopes which we grant to Microsub clients. Mute and block are not included because they have no meaning in an RSS/Atom context; this may signal to clients to suppress muting and blocking in their UI */
    const SCOPES = "read follow channels";
    const FUNCTIONS = [
        'discovery' => ['GET' => "opDiscovery"],
        'login'     => ['GET' => "opLogin", 'POST' => "opCodeVerification"],
        'issue'     => ['POST' => "opIssue"],
    ];

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
        $process = $req->getQueryParams()['proc'] ?? "discovery";
        $method = $req->getMethod();
        if (isset(self::FUNCTIONS[$process])) {
            return new EmptyResponse(404);
        } elseif ($method === "OPTIONS") {
            $fields = ['Allow' => implode(",", array_keys(self::FUNCTIONS[$process]))];
            if (isset(self::FUNCTIONS[$process]['POST'])) {
                $fields['Accept'] = "application/x-www-form-urlencoded";
            }
            return new EmptyResponse(204, $fields);
        } elseif (isset(self::FUNCTIONS[$process][$method])) {
            return new EmptyResponse(405, ['Allow' => implode(",", array_keys(self::FUNCTIONS[$process]))]);
        } else {
            $func = self::FUNCTIONS[$process][$method];
            return $this->$func($id, $req);
        }
    }

    /** Produces a user-identifier URL consiustent with the request
     * 
     * This involves reconstructing the scheme and authority based on $_SERVER
     * variables; it may fail depending on server configuration
     */
    protected function buildIdentifier(ServerRequestInterface $req, bool $baseOnly = false): string {
        // construct the base user identifier URL; the user is never checked against the database
        $s = $req->getServerParams();
        $path = $req->getRequestTarget()['path'];
        $https = (strlen($s['HTTPS'] ?? "") && $s['HTTPS'] !== "off");
        $port = (int) ($s['SERVER_PORT'] ?? 0);
        $port = (!$port || ($https && $port == 443) || (!$https && $port == 80)) ? "" : ":$port";
        $base = URL::normalize(($https ? "https" : "http")."://".$s['HTTP_HOST'].$port."/");
        return !$baseOnly ? URL::normalize($base.$path) : $base;
    }

    /** Presents a very basic user profile for discovery purposes
     * 
     * The HTML document itself consists only of link elements and an 
     * encoding declaration; Link header-fields are also included for 
     * HEAD requests
     * 
     * Since discovery is publicly accessible, we produce a discovery
     * page for all potential user name so as not to facilitate user
     * enumeration
     * 
     * @see https://indieweb.org/Microsub-spec#Discovery
     */
    protected function opDiscovery(string $user, ServerRequestInterface $req): ResponseInterface {
        $base = $this->buildIdentifier($req, true);
        $id = $this->buildIdentifier($req);
        $urlAuth = $id."?proc=login";
        $urlToken = $id."?proc=issue";
        $urlService = $base."microsub";
        // output an extremely basic identity resource
        $html = '<meta charset="UTF-8"><link rel="authorization_endpoint" href="'.htmlspecialchars($urlAuth).'"><link rel="token_endpoint" href="'.htmlspecialchars($urlToken).'"><link rel="microsub" href="'.htmlspecialchars($urlService).'">';
        return new HtmlResponse($html, 200, [
            "Link: <$urlAuth>; rel=\"authorization_endpoint\"",
            "Link: <$urlToken>; rel=\"token_endpoint\"",
            "Link: <$urlService>; rel=\"microsub\"",
        ]);
    }

    /** Handles the authentication/authorization process
     * 
     * Authentication is achieved via an HTTP Basic authentiation
     * challenge; once the user successfully logs in a code is issued 
     * and redirection occurs. Scopes are for all intents and purposes
     * ignored and client information is not presented. 
     * 
     * @see https://indieauth.spec.indieweb.org/#authentication-request
     */
    protected function opLogin(string $user, ServerRequestInterface $req): ResponseInterface {
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
                // store the client ID and redirect URL
                $data = json_encode([
                    'id' => $query['client_id'],
                    'url' => $query['redirect_uri'],
                ],\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                // issue an authorization code and build the redirect URL
                $code = Arsse::$db->tokenCreate($id, "microsub.auth", null, Date::add("PT2M"), $data);
                $next = URL::queryAppend($redir, "code=$code&state=$state");
                return new EmptyResponse(302, ["Location: $next"]);
            }
        }
    }

    /** Validates an authorization code against client-provided values
     * 
     * The redirect URL and client ID are checked, as is the user ID
     * 
     * If everything checks out the canonical user URL is supposed to be returned;
     * we don't actually know what the canonical URL is modulo URL encoding, but it
     * doesn't actually matter for our purposes
     * 
     * @see https://indieauth.spec.indieweb.org/#authorization-code-verification
     */
    protected function opCodeVerification(string $user, ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
        try {
            // validate the request parameters
            $code = $post['code'] ?? "";
            $id = $post['client_id'] ?? "";
            $url = $post['redirect_uri'] ?? "";
            if (!strlen($code) || !strlen($id) || !strlen($url)) {
                throw new ExceptionAuth("invalid_request");
            }
            // check that the token exists
            $token = Arsse::$db->tokenLookup("microsub.auth", $code);
            if (!$token) {
                throw new ExceptionAuth("unsupported_grant_type");
            }
            $data = @json_decode($token['data'], true);
            // validate the token
            if ($token['user'] !== $user || !is_array($data) || $data['id'] !== $id || $data['url'] !== $url) {
                throw new ExceptionAuth("unsupported_grant_type");
            } else {
                return new JsonResponse(['me' => $this->buildIdentifier($req)]);
            }
        } catch (ExceptionAuth $e) {
            // human-readable error messages could be added, but these must be ASCII per OAuth, so there's probably not much point
            // see https://tools.ietf.org/html/rfc6749#section-5.2
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    protected function opIssue(string $user, ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
    }
}
