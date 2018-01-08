<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\EmptyResponse;

class REST {
    const API_LIST = [
        // NextCloud News version enumerator
        'ncn' => [
            'match' => '/index.php/apps/news/api',
            'strip' => '/index.php/apps/news/api',
            'class' => REST\NextCloudNews\Versions::class,
        ],
        // NextCloud News v1-2  https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md
        'ncn_v1-2' => [
            'match' => '/index.php/apps/news/api/v1-2/',
            'strip' => '/index.php/apps/news/api/v1-2',
            'class' => REST\NextCloudNews\V1_2::class,
        ],
        'ttrss_api' => [ // Tiny Tiny RSS  https://git.tt-rss.org/git/tt-rss/wiki/ApiReference
            'match' => '/tt-rss/api',
            'strip' => '/tt-rss/api',
            'class' => REST\TinyTinyRSS\API::class,
        ],
        'ttrss_icon' => [ // Tiny Tiny RSS feed icons
            'match' => '/tt-rss/feed-icons/',
            'strip' => '/tt-rss/feed-icons/',
            'class' => REST\TinyTinyRSS\Icon::class,
        ],
        // Other candidates:
        // Google Reader        http://feedhq.readthedocs.io/en/latest/api/index.html
        // Fever                https://feedafever.com/api
        // Feedbin v2           https://github.com/feedbin/feedbin-api
        // CommaFeed            https://www.commafeed.com/api/
        // Unclear if clients exist:
        // Miniflux             https://github.com/miniflux/miniflux/blob/master/docs/json-rpc-api.markdown
        // NextCloud News v2    https://github.com/nextcloud/news/blob/master/docs/externalapi/External-Api.md
        // Selfoss              https://github.com/SSilence/selfoss/wiki/Restful-API-for-Apps-or-any-other-external-access
        // BirdReader           https://github.com/glynnbird/birdreader/blob/master/API.md
        // Feedbin v1           https://github.com/feedbin/feedbin-api/commit/86da10aac5f1a57531a6e17b08744e5f9e7db8a9
        // Proprietary (centralized) entities:
        // NewsBlur             http://www.newsblur.com/api
        // Feedly               https://developer.feedly.com/
    ];
    protected $apis = [];

    public function __construct(array $apis = null) {
        $this->apis = $apis ?? self::API_LIST;
    }

    public function dispatch(ServerRequestInterface $req = null): ResponseInterface {
        // create a request object if not provided
        $req = $req ?? ServerRequestFactory::fromGlobals();
        // find the API to handle 
        try {
            list ($api, $target, $class) = $this->apiMatch($req->getRequestTarget(), $this->apis);
            // modify the request to have an uppercase method and a stripped target
            $req = $req->withMethod(strtoupper($req->getMethod()))->withRequestTarget($target);
            // fetch the correct handler
            $drv = $this->getHandler($class);
            // generate a response
            if ($req->getMethod()=="HEAD") {
                // if the request is a HEAD request, we act exactly as if it were a GET request, and simply remove the response body later
                $res = $drv->dispatch($req->withMethod("GET"));
            } else {
                $res = $drv->dispatch($req);
            }
        } catch (REST\Exception501 $e) {
            $res = new EmptyResponse(501);
        }
        // modify the response so that it has all the required metadata
        return $this->normalizeResponse($res, $req);
    }

    public function getHandler(string $className): REST\Handler {
        // instantiate the API handler
        return new $className();
    }

    public function apiMatch(string $url): array {
        $map = $this->apis;
        // sort the API list so the longest URL prefixes come first
        uasort($map, function ($a, $b) {
            return (strlen($a['match']) <=> strlen($b['match'])) * -1;
        });
        // normalize the target URL
        $url = REST\Target::normalize($url);
        // find a match
        foreach ($map as $id => $api) {
            // first try a simple substring match
            if (strpos($url, $api['match'])===0) {
                // if it matches, perform a more rigorous match and then strip off any defined prefix
                $pattern = "<^".preg_quote($api['match'])."([/\?#]|$)>";
                if ($url==$api['match'] || in_array(substr($api['match'], -1, 1), ["/", "?", "#"]) || preg_match($pattern, $url)) {
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

    public function normalizeResponse(ResponseInterface $res, RequestInterface $req = null): ResponseInterface {
        // set or clear the Content-Length header field
        $body = $res->getBody();
        $bodySize = $body->getSize();
        if ($bodySize || $res->getStatusCode()==200) {
            // if there is a message body or the response is 200, make sure Content-Length is included
            $res = $res->withHeader("Content-Length", (string) $bodySize);
        } else {
            // for empty responses of other statuses, omit it
            $res = $res->withoutHeader("Content-Length");
        }
        // if the response is to a HEAD request, the body should be omitted
        if ($req && $req->getMethod()=="HEAD") {
            $res = new EmptyResponse($res->getStatusCode(), $res->getHeaders());
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
        return $res;
    }
}
