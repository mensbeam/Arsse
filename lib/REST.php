<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\URL;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\ServerRequest;

class REST {
    public const API_LIST = [
        'ncn' => [ // Nextcloud News version enumerator
            'match' => '/index.php/apps/news/api',
            'strip' => '/index.php/apps/news/api',
            'class' => REST\NextcloudNews\Versions::class,
        ],
        'ncn_v1-2' => [ // Nextcloud News v1-2  https://github.com/nextcloud/news/blob/master/docs/api/api-v1-2.md
            'match' => '/index.php/apps/news/api/v1-2/',
            'strip' => '/index.php/apps/news/api/v1-2',
            'class' => REST\NextcloudNews\V1_2::class,
        ],
        'ncn_v1-3' => [ // Nextcloud News v1-3  https://github.com/nextcloud/news/blob/master/docs/api/api-v1-3.md
            'match' => '/index.php/apps/news/api/v1-3/',
            'strip' => '/index.php/apps/news/api/v1-3',
            'class' => REST\NextcloudNews\V1_3::class,
        ],
        'ttrss_api' => [ // Tiny Tiny RSS  https://tt-rss.org/ApiReference/
            'match' => '/tt-rss/api',
            'strip' => '/tt-rss/api',
            'class' => REST\TinyTinyRSS\API::class,
        ],
        'ttrss_icon' => [ // Tiny Tiny RSS feed icons
            'match' => '/tt-rss/feed-icons/',
            'strip' => '/tt-rss/feed-icons/',
            'class' => REST\TinyTinyRSS\Icon::class,
        ],
        'fever' => [ // Fever  https://web.archive.org/web/20161217042229/https://feedafever.com/api
            'match' => '/fever/',
            'strip' => '/fever/',
            'class' => REST\Fever\API::class,
        ],
        'miniflux' => [ // Miniflux  https://miniflux.app/docs/api.html
            'match' => '/v1/',
            'strip' => '/v1',
            'class' => REST\Miniflux\V1::class,
        ],
        'miniflux-version' => [ // Miniflux version report
            'match' => '/version',
            'strip' => '',
            'class' => REST\Miniflux\Status::class,
        ],
        'miniflux-healthcheck' => [ // Miniflux health check
            'match' => '/healthcheck',
            'strip' => '',
            'class' => REST\Miniflux\Status::class,
        ],
        // Other candidates:
        // Microsub             https://indieweb.org/Microsub
        // Google Reader        http://feedhq.readthedocs.io/en/latest/api/index.html
        // Feedbin v2           https://github.com/feedbin/feedbin-api
        // CommaFeed            https://www.commafeed.com/api/
        // Selfoss              https://github.com/SSilence/selfoss/wiki/Restful-API-for-Apps-or-any-other-external-access
        // NewsBlur             http://www.newsblur.com/api
        // Unclear if clients exist:
        // Nextcloud News v2    https://github.com/nextcloud/news/blob/master/docs/externalapi/External-Api.md
        // BirdReader           https://github.com/glynnbird/birdreader/blob/master/API.md
        // Feedbin v1           https://github.com/feedbin/feedbin-api/commit/86da10aac5f1a57531a6e17b08744e5f9e7db8a9
        // Proprietary (centralized) entities:
        // Feedly               https://developer.feedly.com/
    ];
    protected const DEFAULT_PORTS = [
        'http'  => 80,
        'https' => 443,
    ];
    protected $apis = [];

    public function __construct(?array $apis = null) {
        $this->apis = $apis ?? self::API_LIST;
    }

    public function dispatch(?ServerRequestInterface $req = null): ResponseInterface {
        try {
            // ensure the require extensions are loaded
            Arsse::checkExtensions(...Arsse::REQUIRED_EXTENSIONS);
            // create a request object if not provided
            $req = $req ?? ServerRequest::fromGlobals();
            // find the API to handle
            [, $target, $class] = $this->apiMatch($req->getRequestTarget(), $this->apis);
            // authenticate the request pre-emptively
            $req = $this->authenticateRequest($req);
            // modify the request to have an uppercase method and a stripped target
            $req = $req->withMethod(strtoupper($req->getMethod()))->withRequestTarget($target);
            // fetch the correct handler
            $drv = Arsse::$obj->get($class);
            // generate a response
            if ($req->getMethod() === "HEAD") {
                // if the request is a HEAD request, we act exactly as if it were a GET request, and simply remove the response body later
                $res = $drv->dispatch($req->withMethod("GET"));
            } else {
                $res = $drv->dispatch($req);
            }
        } catch (REST\Exception501 $e) {
            $res = HTTP::respEmpty(501);
        }
        // modify the response so that it has all the required metadata
        return $this->normalizeResponse($res, $req);
    }

    public function apiMatch(string $url): array {
        $map = $this->apis;
        // sort the API list so the longest URL prefixes come first
        uasort($map, function($a, $b) {
            return (strlen($a['match']) <=> strlen($b['match'])) * -1;
        });
        // normalize the target URL
        $url = URL::normalize($url);
        // find a match
        foreach ($map as $id => $api) {
            // first try a simple substring match
            if (strpos($url, $api['match']) === 0) {
                // if it matches, perform a more rigorous match and then strip off any defined prefix
                $pattern = "<^".preg_quote($api['match'])."([/\?#]|$)>D";
                if ($url === $api['match'] || in_array(substr($api['match'], -1, 1), ["/", "?", "#"]) || preg_match($pattern, $url)) {
                    $target = substr($url, strlen($api['strip']));
                } else {
                    // if the match fails we are not able to handle the request
                    throw new REST\Exception501();
                }
                // return the API name, stripped URL, and API class name
                return [$id, $target, $api['class']];
            }
        }
        // or throw an exception otherwise
        throw new REST\Exception501();
    }

    public function authenticateRequest(ServerRequestInterface $req): ServerRequestInterface {
        $user = "";
        $password = "";
        $env = $req->getServerParams();
        if (isset($env['PHP_AUTH_USER'])) {
            $user = $env['PHP_AUTH_USER'];
            if (isset($env['PHP_AUTH_PW'])) {
                $password = $env['PHP_AUTH_PW'];
            }
        } elseif (isset($env['REMOTE_USER'])) {
            $user = $env['REMOTE_USER'];
        }
        if (strlen($user)) {
            if (Arsse::$user->auth((string) $user, (string) $password)) {
                $req = $req->withAttribute("authenticated", true);
                $req = $req->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        return $req;
    }

    public function challenge(ResponseInterface $res, ?string $realm = null): ResponseInterface {
        $realm = $realm ?? Arsse::$conf->httpRealm;
        return $res->withAddedHeader("WWW-Authenticate", 'Basic realm="'.$realm.'", charset="UTF-8"');
    }

    public function normalizeResponse(ResponseInterface $res, ?RequestInterface $req = null): ResponseInterface {
        // if the response code is 401, issue an HTTP authentication challenge
        if ($res->getStatusCode() == 401) {
            $res = $this->challenge($res);
        }
        // set or clear the Content-Length header field
        $body = $res->getBody();
        $bodySize = $body->getSize();
        if ($bodySize || $res->getStatusCode() == 200) {
            // if there is a message body or the response is 200, make sure Content-Length is included
            $res = $res->withHeader("Content-Length", (string) $bodySize);
        } else {
            // for empty responses of other statuses, omit it
            $res = $res->withoutHeader("Content-Length");
        }
        // if the response is to a HEAD request, the body should be omitted
        if ($req && $req->getMethod() === "HEAD") {
            $res = HTTP::respEmpty($res->getStatusCode(), $res->getHeaders());
        }
        // if an Allow header field is present, normalize it
        if ($res->hasHeader("Allow")) {
            $methods = preg_split("<\s*,\s*>", strtoupper($res->getHeaderLine("Allow")));
            // if GET is allowed, HEAD should be allowed as well
            if (in_array("GET", $methods) && !in_array("HEAD", $methods)) {
                $methods[] = "HEAD";
            }
            // OPTIONS requests are always allowed by our handlers
            if (!in_array("OPTIONS", $methods)) {
                $methods[] = "OPTIONS";
            }
            $res = $res->withHeader("Allow", implode(", ", $methods));
        }
        // add CORS header fields if the request origin is specified and allowed
        if ($req && $this->corsNegotiate($req)) {
            $res = $this->corsApply($res, $req);
        }
        return $res;
    }

    public function corsApply(ResponseInterface $res, ?RequestInterface $req = null): ResponseInterface {
        if ($req && $req->getMethod() === "OPTIONS") {
            if ($res->hasHeader("Allow")) {
                $res = $res->withHeader("Access-Control-Allow-Methods", $res->getHeaderLine("Allow"));
            }
            if ($req->hasHeader("Access-Control-Request-Headers")) {
                $res = $res->withHeader("Access-Control-Allow-Headers", $req->getHeaderLine("Access-Control-Request-Headers"));
            }
            $res = $res->withHeader("Access-Control-Max-Age", (string) (60 * 60 * 24)); // one day
        }
        $res = $res->withHeader("Access-Control-Allow-Origin", $req->getHeaderLine("Origin"));
        $res = $res->withHeader("Access-Control-Allow-Credentials", "true");
        return $res->withAddedHeader("Vary", "Origin");
    }

    public function corsNegotiate(RequestInterface $req, ?string $allowed = null, ?string $denied = null): bool {
        $allowed = trim($allowed ?? Arsse::$conf->httpOriginsAllowed);
        $denied = trim($denied ?? Arsse::$conf->httpOriginsDenied);
        // continue if at least one origin is allowed
        if ($allowed) {
            // continue if the request has exactly one Origin header
            $origin = $req->getHeader("Origin");
            if (sizeof($origin) == 1) {
                // continue if the origin is syntactically valid
                $origin = $this->corsNormalizeOrigin($origin[0]);
                if ($origin) {
                    // the special "null" origin should not be matched by the wildcard origin
                    $null = ($origin === "null");
                    // pad all strings for simpler comparison
                    $allowed = " ".$allowed." ";
                    $denied = " ".$denied." ";
                    $origin = " ".$origin." ";
                    $any = " * ";
                    if (strpos($denied, $origin) !== false) {
                        // first check the denied list for the origin
                        return false;
                    } elseif (strpos($allowed, $origin) !== false) {
                        // next check the allowed list for the origin
                        return true;
                    } elseif (!$null && strpos($denied, $any) !== false) {
                        // next check the denied list for the wildcard origin
                        return false;
                    } elseif (!$null && strpos($allowed, $any) !== false) {
                        // finally check the allowed list for the wildcard origin
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function corsNormalizeOrigin(string $origin, ?array $ports = null): string {
        $origin = trim($origin);
        if ($origin === "null") {
            // if the origin is the special value "null", use it
            return "null";
        }
        if (preg_match("<^([^:]+)://(\[[^\]]+\]|[^\[\]:/\?#@]+)((?::.*)?)$>Di", $origin, $match)) {
            // if the origin sort-of matches the syntax in a general sense, continue
            $scheme = $match[1];
            $host = $match[2];
            $port = $match[3];
            // decode and normalize the scheme and port (the port may be blank)
            $scheme = strtolower(rawurldecode($scheme));
            $port = rawurldecode($port);
            if (!preg_match("<^(?::[0-9]+)?$>D", $port) || !preg_match("<^[a-z](?:[a-z0-9\+\-\.])*$>D", $scheme)) {
                // if the normalized port contains anything but numbers, or the scheme does not follow the generic URL syntax, the origin is invalid
                return "";
            }
            if ($host[0] === "[") {
                // if the host appears to be an IPv6 address, validate it
                $host = rawurldecode(substr($host, 1, strlen($host) - 2));
                if (!filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                    return "";
                } else {
                    $host = "[".inet_ntop(inet_pton($host))."]";
                }
            } else {
                // if the host is a domain name or IP address, split it along dots and just perform URL decoding
                $host = explode(".", $host);
                $host = array_map(function($segment) {
                    return str_replace(".", "%2E", rawurlencode(strtolower(rawurldecode($segment))));
                }, $host);
                $host = implode(".", $host);
            }
            // suppress default ports
            if (strlen($port)) {
                $port = (int) substr($port, 1);
                $list = array_merge($ports ?? [], self::DEFAULT_PORTS);
                if (isset($list[$scheme]) && $port == $list[$scheme]) {
                    $port = "";
                } else {
                    $port = ":".$port;
                }
            }
            // return the reconstructed result
            return $scheme."://".$host.$port;
        } else {
            return "";
        }
    }
}
