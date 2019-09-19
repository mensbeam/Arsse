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
    /** The list of the logical functions of this API, with their implementations */
    const FUNCTIONS = [
        'discovery' => ['GET' => "opDiscovery"],
        'auth'      => ['GET' => "opLogin",             'POST' => "opCodeVerification"],
        'token'     => ['GET' => "opTokenVerification", 'POST' => "opIssueAccessToken"],
    ];
    /** The minimal set of reserved URL characters which must be escaped when comparing user ID URLs */
    const USERNAME_ESCAPES = [
        '#' => "%23",
        '%' => "%25",
        '/' => "%2F",
        '?' => "%3F",
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // if the path contains a slash, this is not a URL we handle
        $path = parse_url($req->getRequestTarget())['path'] ?? "";
        if (strpos($path, "/") !== false) {
            return new EmptyResponse(404);
        }
        // gather the query parameters and act on the "f" (function) parameter
        $process = $req->getQueryParams()['f'] ?? "";
        $process = strlen($process) ? $process : "discovery";
        $method = $req->getMethod();
        if (isset(self::FUNCTIONS[$process]) || ($process === "discovery" && !strlen($path)) || ($process !== "discovery" && strlen($path))) {
            // the function requested needs to exist
            // the path should also be empty unless we're doing discovery
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
            try {
                $func = self::FUNCTIONS[$process][$method];
                return $this->$func($req);
            } catch (ExceptionAuth $e) {
                // human-readable error messages could be added, but these must be ASCII per OAuth, so there's probably not much point
                // see https://tools.ietf.org/html/rfc6749#section-5.2
                return new JsonResponse(['error' => $e->getMessage()], 400);
            }
        }
    }

    /** Produces the base URL of a server request
     * 
     * This involves reconstructing the scheme and authority based on $_SERVER
     * variables; it may fail depending on server configuration
     */
    protected function buildBaseURL(ServerRequestInterface $req): string {
        // construct the base user identifier URL; the user is never checked against the database
        $s = $req->getServerParams();
        $path = $req->getRequestTarget()['path'];
        $https = (strlen($s['HTTPS'] ?? "") && $s['HTTPS'] !== "off");
        $port = (int) ($s['SERVER_PORT'] ?? 0);
        $port = (!$port || ($https && $port == 443) || (!$https && $port == 80)) ? "" : ":$port";
        return URL::normalize(($https ? "https" : "http")."://".$s['HTTP_HOST'].$port."/");
    }

    protected function buildIdentifier(ServerRequestInterface $req, string $user): string {
        return $this->buildBaseURL($req)."u/".str_replace(array_keys(self::USERNAME_ESCAPES), array_values(self::USERNAME_ESCAPES), $user);
    }

    protected function matchIdentifier(string $canonical, string $me): bool {
        $me = parse_url(URL::normalize($me));
        $me['scheme'] = $me['scheme'] ?? "";
        $me['path'] = explode("/", $me['path'] ?? "");
        $me['id'] = rawurldecode(array_pop($me['path']) ?? "");
        $me['port'] == (($me['scheme'] === "http" && $me['port'] == 80) || ($me['scheme'] === "https" && $me['port'] == 443)) ? 0 : $me['port'];
        $c = parse_url($canonical);
        $c['path'] = explode("/", $c['path']);
        $c['id'] = rawurldecode(array_pop($c['path']));
        if (
            !in_array($me['scheme'] ?? "", ["http", "https"]) || 
            ($me['host'] ?? "") !== $c['host'] ||
            $me['path'] != $c['path'] ||
            $me['id'] !== $c['id'] ||
            strlen($me['user'] ?? "") ||
            strlen($me['pass'] ?? "") ||
            strlen($me['query'] ?? "") ||
            ($me['port'] ?? 0) != ($c['port'] ?? 0)
        ) {
            return false;
        }
        return true;
    }

    /** Presents a very basic user profile for discovery purposes
     * 
     * The HTML document itself consists only of link elements and an 
     * encoding declaration; Link header-fields are also included for 
     * HEAD requests
     * 
     * Since discovery is publicly accessible, we produce a discovery
     * page for all potential user names so as not to facilitate user
     * enumeration
     * 
     * @see https://indieweb.org/Microsub-spec#Discovery
     */
    protected function opDiscovery(ServerRequestInterface $req): ResponseInterface {
        $base = $this->buildBaseURL($req);
        $urlAuth = $base."u/?f=auth";
        $urlToken = $base."u/?f=token";
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
    protected function opLogin(ServerRequestInterface $req): ResponseInterface {
        if (!$req->getAttribute("authenticated", false)) {
            // user has not yet logged in, or has failed to log in
            return new EmptyResponse(401);
        } else {
            // user has logged in
            $query = $req->getQueryParams();
            $redir = URL::normalize(rawurldecode($query['redirect_uri']));
            // check that the redirect URL is an absolute one
            if (!URL::absolute($redir)) {
                return new EmptyResponse(400);
            }
            try {
                // ensure the logged-in user matches the IndieAuth identifier URL
                $user = $req->getAttribute("authenticatedUser");
                if (!$this->matchIdentifier($this->buildIdentifier($req, $user), $query['me'])) {
                        throw new ExceptionAuth("access_denied");
                }
                $type = !strlen($query['response_type'] ?? "") ? "id" : $query['response_type'];
                if (!in_array($type, ["code", "id"])) {
                    throw new ExceptionAuth("unsupported_response_type");
                }
                $state = $query['state'] ?? "";
                // store the identity URL, client ID, redirect URL, and response type
                $data = json_encode([
                    'me' => $query['me'],
                    'client' => $query['client_id'],
                    'redir' => $query['redirect_uri'],
                    'type' => $type,
                ],\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                // issue an authorization code and build the redirect URL
                $code = Arsse::$db->tokenCreate($user, "microsub.auth", null, Date::add("PT2M"), $data);
                $next = URL::queryAppend($redir, "code=$code&state=$state");
                return new EmptyResponse(302, ["Location: $next"]);
            } catch (ExceptionAuth $e) {
                $next = URL::queryAppend($redir, "state=$state&error=".$e->getMessage());
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
     * @see https://indieauth.spec.indieweb.org/#authorization-code-verification-0
     */
    protected function opCodeVerification(ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
        // validate the request parameters
        $code = $post['code'] ?? "";
        $client = $post['client_id'] ?? "";
        $redir = $post['redirect_uri'] ?? "";
        if (!strlen($code) || !strlen($client) || !strlen($redir)) {
            throw new ExceptionAuth("invalid_request");
        }
        // check that the token exists
        $token = Arsse::$db->tokenLookup("microsub.auth", $code);
        if (!$token) {
            throw new ExceptionAuth("invalid_grant");
        }
        $data = @json_decode($token['data'], true);
        // validate the token
        if (!is_array($data)) {
            throw new ExceptionAuth("invalid_grant");
        } elseif ($data['client'] !== $client || $data['redir'] !== $redir) {
            throw new ExceptionAuth("invalid_client");
        } else {
            $out = ['me' => $this->buildIdentifier($req, $token['user'])];
            if ($data['type'] === "code") {
                $out['scope'] = self::SCOPES;
            }
            return new JsonResponse($out);
        }
    }

    protected function opIssueAccessToken(ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
        $type = $post['grant_type'] ?? "";
        $me = $post['me'] ?? "";
        if ($type !== "authorization_code") {
            throw new ExceptionAuth("unsupported_grant_type");
        } elseif ($this->buildIdentifier($req) !== $me) {
            throw new ExceptionAuth("invalid_grant");
        } else {
            $out = $this->opCodeVerification($user, $req)->getPayload();
            if (!isset($out['scope'])) {
                throw new ExceptionAuth("invalid_scope");
            }
            // issue an access token
            $tr = Arsse::$db->begin();
            $token = Arsse::$db->tokenCreate($user, "microsub.access");
            Arsse::$db->tokenRevoke($user, "microsub.auth", $post['code']);
            $tr->commit();
            $out['access_token'] = $token;
            $out['token_type'] = "Bearer";
            return new JsonResponse($out);
        }
    }

    protected function opTokenVerification(string $user, ServerRequestInterface $req): ResponseInterface {
        
    }
}
