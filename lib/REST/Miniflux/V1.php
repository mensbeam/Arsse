<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Exception;
use JKingWeb\Arsse\REST\Exception404;
use JKingWeb\Arsse\REST\Exception405;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse as Response;
use Laminas\Diactoros\Response\EmptyResponse;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    protected $paths = [
        '/categories'         => ['GET'  => "getCategories",  'POST'   => "createCategory"],
        '/categories/1'       => ['PUT'  => "updateCategory", 'DELETE' => "deleteCategory"],
        '/discover'           => ['POST' => "discoverSubscriptions"],
        '/entries'            => ['GET'  => "getEntries",     'PUT'    => "updateEntries"],
        '/entries/1'          => ['GET'  => "getEntry"],
        '/entries/1/bookmark' => ['PUT'  => "toggleEntryBookmark"],
        '/export'             => ['GET'  => "opmlExport"],
        '/feeds'              => ['GET'  => "getFeeds",       'POST'   => "createFeed"],
        '/feeds/1'            => ['GET'  => "getFeed",        'PUT'    => "updateFeed", 'DELETE' => "removeFeed"],
        '/feeds/1/entries/1'  => ['GET'  => "getFeedEntry"],
        '/feeds/1/entries'    => ['GET'  => "getFeedEntries"],
        '/feeds/1/icon'       => ['GET'  => "getFeedIcon"],
        '/feeds/1/refresh'    => ['PUT'  => "refreshFeed"],
        '/feeds/refresh'      => ['PUT'  => "refreshAllFeeds"],
        '/healthcheck'        => ['GET'  => "healthCheck"],
        '/import'             => ['POST' => "opmlImport"],
        '/me'                 => ['GET'  => "getCurrentUser"],
        '/users'              => ['GET'  => "getUsers",       'POST' => "createUser"],
        '/users/1'            => ['GET'  => "getUser",        'PUT'  => "updateUser",   'DELETE' => "deleteUser"],
        '/users/*'            => ['GET'  => "getUser"],
        '/version'            => ['GET'  => "getVersion"],
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // try to authenticate
        if ($req->getAttribute("authenticated", false)) {
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        } else {
            return new EmptyResponse(401);
        }
        // get the request path only; this is assumed to already be normalized
        $target = parse_url($req->getRequestTarget())['path'] ?? "";
        // handle HTTP OPTIONS requests
        if ($req->getMethod() === "OPTIONS") {
            return $this->handleHTTPOptions($target);
        }
    }

    protected function normalizePathIds(string $url): string {
        $path = explode("/", $url);
        // any path components which are database IDs (integers greater than zero) should be replaced with "1", for easier comparison (we don't care about the specific ID)
        for ($a = 0; $a < sizeof($path); $a++) {
            if (ValueInfo::id($path[$a])) {
                $path[$a] = "1";
            }
        }
        return implode("/", $path);
    }

    protected function handleHTTPOptions(string $url): ResponseInterface {
        // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIDs($url);
        if (isset($this->paths[$url])) {
            // if the path is supported, respond with the allowed methods and other metadata
            $allowed = array_keys($this->paths[$url]);
            // if GET is allowed, so is HEAD
            if (in_array("GET", $allowed)) {
                array_unshift($allowed, "HEAD");
            }
            return new EmptyResponse(204, [
                'Allow'  => implode(",", $allowed),
                'Accept' => self::ACCEPTED_TYPE,
            ]);
        } else {
            // if the path is not supported, return 404
            return new EmptyResponse(404);
        }
    }

    protected function chooseCall(string $url, string $method): string {
        // // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIds($url);
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // we now evaluate the supplied URL against every supported path for the selected scope
        // the URL is evaluated as an array so as to avoid decoded escapes turning invalid URLs into valid ones
        if (isset($this->paths[$url])) {
            // if the path is supported, make sure the method is allowed
            if (isset($this->paths[$url][$method])) {
                // if it is allowed, return the object method to run
                return $this->paths[$url][$method];
            } else {
                // otherwise return 405
                throw new Exception405(implode(", ", array_keys($this->paths[$url])));
            }
        } else {
            // if the path is not supported, return 404
            throw new Exception404();
        }
    }
}
