<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Microsub;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\URL;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Misc\ValueInfo;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

class Auth extends \JKingWeb\Arsse\REST\AbstractHandler {
    /** The scopes which we grant to Microsub clients. Mute and block are not included because they have no meaning in an RSS/Atom context; this may signal to clients to suppress muting and blocking in their UI */
    const SCOPES = ["read", "follow", "channels"];
    /** The list of the logical functions of this API, with their implementations */
    const FUNCTIONS = [
        ''      => ['GET' => "opDiscovery"],
        'auth'  => ['GET' => "opLogin",             'POST' => "opCodeVerification"],
        'token' => ['GET' => "opTokenVerification", 'POST' => "opIssueAccessToken"],
    ];
    /** The set of URL characters escaped by rawurlencode() which should be unescaped when constructing user ID URLs */
    const USERNAME_UNESCAPES = [
        '%21' => "!",
        '%24' => "$",
        '%26' => "&",
        '%27' => "'",
        '%28' => "(",
        '%29' => ")",
        '%2A' => "*",
        '%2B' => "+",
        '%2C' => ",",
        '%3A' => ":",
        '%3B' => ";",
        '%3D' => "=",
        '%40' => "@",
    ];
    /** The acceptable media type of input for POST requests */
    const ACCEPTED_TYPE = "application/x-www-form-urlencoded";

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
        $method = $req->getMethod();
        if (!isset(self::FUNCTIONS[$process]) || ($process === "" && !strlen($path)) || ($process !== "" && strlen($path))) {
            // the function requested needs to exist
            // the path should also be empty unless we're doing discovery
            return new EmptyResponse(404);
        } elseif ($method === "OPTIONS") {
            $fields = ['Allow' => implode(",", array_keys(self::FUNCTIONS[$process]))];
            if (isset(self::FUNCTIONS[$process]['POST'])) {
                $fields['Accept'] = self::ACCEPTED_TYPE;
            }
            return new EmptyResponse(204, $fields);
        } elseif (!isset(self::FUNCTIONS[$process][$method])) {
            return new EmptyResponse(405, ['Allow' => implode(",", array_keys(self::FUNCTIONS[$process]))]);
        } else {
            if ($req->getMethod() !== "GET" && !HTTP::matchType($req, self::ACCEPTED_TYPE, "")) {
                return new EmptyResponse(415, ['Accept' => self::ACCEPTED_TYPE]);
            }
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
        $https = ValueInfo::normalize($s['HTTPS'] ?? "", ValueInfo::T_BOOL);
        $port = (int) ($s['SERVER_PORT'] ?? 0);
        $port = (!$port || ($https && $port == 443) || (!$https && $port == 80)) ? "" : ":$port";
        return URL::normalize(($https ? "https" : "http")."://".$s['HTTP_HOST'].$port."/");
    }

    /** Produces a canoncial identity URL based on a server request and a user name
     *
     * This involves reconstructing the scheme and authority based on $_SERVER
     * variables; it may fail depending on server configuration
     */
    protected function buildIdentifier(ServerRequestInterface $req, string $user): string {
        return $this->buildBaseURL($req)."u/".str_replace(array_keys(self::USERNAME_UNESCAPES), array_values(self::USERNAME_UNESCAPES), rawurlencode($user));
    }

    /** Matches an identity URL against its canoncial form
     *
     * The identifier matches if all of the following are true:
     *
     * 1. The scheme is http or https
     * 2. The normalized hostname matches
     * 3. The port matches after dropping default port numbers
     * 4. No credentials are included in the authority
     * 5. The path is `/u/<username>`
     * 6. There is no query content
     * 7. The username, when URL-decoded, matches
     *
     * Though IndieAuth forbids port numbers and fragments in identifiers, we do not enforce this
     */
    protected function matchIdentifier(string $canonical, string $me): bool {
        $me = parse_url(URL::normalize($me));
        if (!$me) {
            return false;
        }
        $me['scheme'] = $me['scheme'] ?? "";
        $me['path'] = explode("/", $me['path'] ?? "");
        $me['id'] = rawurldecode(array_pop($me['path']) ?? "");
        $me['port'] = (($me['scheme'] === "http" && ($me['port'] ?? 80) == 80) || ($me['scheme'] === "https" && ($me['port'] ?? 443) == 443)) ? 0 : $me['port'] ?? 0;
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
        return new HtmlResponse($html, 200, ['Link' => [
            "<$urlAuth>; rel=\"authorization_endpoint\"",
            "<$urlToken>; rel=\"token_endpoint\"",
            "<$urlService>; rel=\"microsub\"",
        ]]);
    }

    /** Handles the authentication process
     *
     * Authentication is achieved via an HTTP Basic authentiation
     * challenge; once the user successfully logs in a code is issued
     * and redirection occurs. Scopes are for all intents and purposes
     * ignored and client information is not presented.
     *
     * @see https://indieauth.spec.indieweb.org/#authentication-request
     * @see https://indieauth.spec.indieweb.org/#authorization-endpoint-0
     */
    protected function opLogin(ServerRequestInterface $req): ResponseInterface {
        if (!$req->getAttribute("authenticated", false)) {
            // user has not yet logged in, or has failed to log in
            return new EmptyResponse(401);
        } else {
            // user has logged in
            $query = $req->getQueryParams();
            $redir = URL::normalize($query['redirect_uri']);
            // check that the redirect URL is an absolute one
            if (!URL::absolute($redir)) {
                return new EmptyResponse(400);
            }
            try {
                $state = rawurlencode($query['state'] ?? "");
                // ensure the logged-in user matches the IndieAuth identifier URL
                $user = $req->getAttribute("authenticatedUser");
                if (!$this->matchIdentifier($this->buildIdentifier($req, $user), $query['me'])) {
                    throw new ExceptionAuth("access_denied");
                }
                $type = !strlen($query['response_type'] ?? "") ? "id" : $query['response_type'];
                if (!in_array($type, ["code", "id"])) {
                    throw new ExceptionAuth("unsupported_response_type");
                }
                // store the identity URL, client ID, redirect URL, and response type
                $data = json_encode([
                    'me' => $query['me'],
                    'client_id' => $query['client_id'],
                    'redirect_uri' => $query['redirect_uri'],
                    'response_type' => $type,
                ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                // issue an authorization code and build the redirect URL
                $code = Arsse::$db->tokenCreate($user, "microsub.auth", null, Date::add("PT2M"), $data);
                $next = URL::queryAppend($redir, "state=$state&code=$code");
                return new EmptyResponse(302, ['Location' => $next]);
            } catch (ExceptionAuth $e) {
                $next = URL::queryAppend($redir, "state=$state&error=".$e->getMessage());
                return new EmptyResponse(302, ['Location' => $next]);
            }
        }
    }

    /** Handles the auth code verification of the basic "Authentication" flow of IndieAuth
     *
     * This is not used by Microsub, but is part of the IndieAuth specification
     *
     * @see https://indieauth.spec.indieweb.org/#authorization-code-verification
     */
    protected function opCodeVerification(ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
        $tr = Arsse::$db->begin();
        // validate the request parameters; an exception will be thrown if not
        list($user, $type) = $this->validateAuthCode($post['code'] ?? "", $post['client_id'] ?? "", $post['redirect_uri'] ?? "");
        if ($type !== "id") {
            throw new ExceptionAuth("invalid_grant");
        }
        // delete the auth code since it is valid and may only be used once
        Arsse::$db->tokenRevoke($user, "microsub.auth", $post['code']);
        $tr->commit();
        // return the canonical identity URL
        return new JsonResponse(['me' => $this->buildIdentifier($req, $user)]);
    }

    /** Handles the auth code verification and token issuance of the "Authorization" flow of IndieAuth
     *
     * @see https://indieauth.spec.indieweb.org/#token-endpoint-0
     */
    protected function opIssueAccessToken(ServerRequestInterface $req): ResponseInterface {
        $post = $req->getParsedBody();
        // revocation is a special case of POSTing to the token URL
        if (($post['action'] ?? "") === "revoke") {
            return $this->opRevokeToken($req);
        }
        if (($post['grant_type'] ?? "") !== "authorization_code") {
            throw new ExceptionAuth("unsupported_grant_type");
        }
        $tr = Arsse::$db->begin();
        list($user, $type) = $this->validateAuthCode($post['code'] ?? "", $post['client_id'] ?? "", $post['redirect_uri'] ?? "", $post['me'] ?? "");
        if ($type !== "code") {
            throw new ExceptionAuth("invalid_grant");
        }
        // issue an access token
        $data = json_encode([
            'me' => $post['me'],
            'client_id' => $post['client_id'],
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $token = Arsse::$db->tokenCreate($user, "microsub.access", null, null, $data);
        Arsse::$db->tokenRevoke($user, "microsub.auth", $post['code']);
        $tr->commit();
        // return the Bearer token and associated data
        return new JsonResponse([
            'me' => $this->buildIdentifier($req, $user),
            'token_type' => "Bearer",
            'access_token' => $token,
            'scope' => implode(" ", self::SCOPES),
        ]);
    }

    /** Validates an auth code and throws appropriate exceptions otherwise
     *
     * Returns an indexed array containing the username and the grant type (either "id" or "code")
     *
     * It is the responsibility of the calling function to revoke the auth code if the code is ultimately accepted
     */
    protected function validateAuthCode(string $code, string $clientId, string $redirUrl, string $me = null): array {
        if (!strlen($code) || !strlen($clientId) || !strlen($redirUrl) || (isset($me) && !strlen($me))) {
            throw new ExceptionAuth("invalid_request");
        }
        // check that the auth code exists
        try {
            $token = Arsse::$db->tokenLookup("microsub.auth", $code);
        } catch (\JKingWeb\Arsse\Db\ExceptionInput $e) {
            throw new ExceptionAuth("invalid_grant");
        }
        $data = @json_decode((string) $token['data'], true);
        // validate the auth code
        if (!is_array($data) || !isset($data['redirect_uri']) || !isset($data['client_id']) || (isset($me) && !isset($data['me']))) {
            throw new ExceptionAuth("invalid_grant");
        } elseif ($data['client_id'] !== $clientId || $data['redirect_uri'] !== $redirUrl) {
            throw new ExceptionAuth("invalid_client");
        } elseif (isset($me) && $me !== $data['me']) {
            throw new ExceptionAuth("invalid_grant");
        }
        // return the associated user name and the auth-code type
        return [$token['user'], $data['response_type'] ?? "id"];
    }

    /** 
     * Handles token verification as an API call; this will not normally be used since 
     * the token and service endpoints are tightly coupled
     *
     * The static `validateBearer` method should be used to check the validity of a bearer token in normal use
     *
     * @see https://indieauth.spec.indieweb.org/#access-token-verification
     */
    protected function opTokenVerification(ServerRequestInterface $req): ResponseInterface {
        try {
            if (!$req->hasHeader("Authorization")) {
                throw new ExceptionAuth("invalid_token");
            }
            $authorization = $req->getHeader("Authorization");
            if (sizeof($authorization) > 1) {
                throw new ExceptionAuth("invalid_request");
            }
            list($user, $data) = self::validateBearer($authorization[0]);
        } catch (ExceptionAuth $e) {
            $errCode = $e->getMessage();
            $httpCode = [
                'invalid_request' => 400,
                'invalid_token' => 401,
            ][$errCode] ?? 500;
            $out = new EmptyResponse($httpCode, ['WWW-Authenticate' => "Bearer error=\"$errCode\""]);
            if ($httpCode == 401) {
                $out = $out->withHeader("X-Arsse-Suppress-General-Auth", "1");
            }
            return $out;
        }
        return new JsonResponse([
            'me' => $data['me'] ?? "",
            'client_id' => $data['client_id'] ?? "",
            'scope' => implode(" ", (array) ($data['scope'] ?? self::SCOPES)),
        ]);
    }

    /** Handles token revocation
     *
     * @see https://indieauth.spec.indieweb.org/#token-revocation
     */
    protected function opRevokeToken(ServerRequestInterface $req): ResponseInterface {
        $token = ($req->getParsedBody() ?? [])['token'] ?? "";
        if (!strlen($token)) {
            return new EmptyResponse(422);
        }
        try {
            $info = Arsse::$db->tokenLookup("microsub.access", $token);
            Arsse::$db->tokenRevoke($info['user'], "microsub.access", $token);
        } catch (\JKingWeb\Arsse\Db\ExceptionInput $e) {
        }
        return new EmptyResponse(200);
    }

    /** Checks that the supplied bearer token is valid i.e. logs a bearer in
     *
     * Returns an indexed array with the user associated with the token, as well as other data
     *
     * @throws \JKingWeb\Arsse\REST\Microsub\ExceptionAuth
     */
    public static function validateBearer(string $authorization, array $scopes = []): array {
        if (!preg_match("<^Bearer ([a-z0-9\._~/+-]+=*)$>i", $authorization, $match)) {
            throw new ExceptionAuth("invalid_request");
        }
        $token = $match[1];
        try {
            $token = Arsse::$db->tokenLookup("microsub.access", $token);
        } catch (\JKingWeb\Arsse\Db\ExceptionInput $e) {
            throw new ExceptionAuth("invalid_token");
        }
        $data = @json_decode((string) $token['data'], true) ?? [];
        $data['scope'] = $data['scope'] ?? self::SCOPES;
        // scope is hard-coded for now
        if (array_diff($scopes, $data['scope'])) {
            throw new ExceptionAuth("insufficient_scope");
        }
        return [$token['user'], $data];
    }
}
