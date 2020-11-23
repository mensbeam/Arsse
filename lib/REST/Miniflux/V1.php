<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Exception;
use JKingWeb\Arsse\REST\Exception404;
use JKingWeb\Arsse\REST\Exception405;
use JKingWeb\Arsse\User\ExceptionConflict as UserException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    protected const ACCEPTED_TYPES_OPML = ["text/xml", "application/xml", "text/x-opml"];
    protected const ACCEPTED_TYPES_JSON = ["application/json", "text/json"];
    public const VERSION = "2.0.25";

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
        '/import'             => ['POST' => "opmlImport"],
        '/me'                 => ['GET'  => "getCurrentUser"],
        '/users'              => ['GET'  => "getUsers",       'POST' => "createUser"],
        '/users/1'            => ['GET'  => "getUser",        'PUT'  => "updateUser",   'DELETE' => "deleteUser"],
        '/users/*'            => ['GET'  => "getUser"],
    ];

    public function __construct() {
    }

    protected function authenticate(ServerRequestInterface $req): bool {
        // first check any tokens; this is what Miniflux does
        foreach ($req->getHeader("X-Auth-Token") as $t) {
            if (strlen($t)) {
                // a non-empty header is authoritative, so we'll stop here one way or the other
                try {
                    $d = Arsse::$db->tokenLookup("miniflux.login", $t);
                } catch (ExceptionInput $e) {
                    return false;
                }
                Arsse::$user->id = $d->user;
                return true;
            }
        }
        // next check HTTP auth
        if ($req->getAttribute("authenticated", false)) {
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        }
        return false;
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // try to authenticate
        if (!$this->authenticate($req)) {
            return new ErrorResponse("401", 401);
        }
        // get the request path only; this is assumed to already be normalized
        $target = parse_url($req->getRequestTarget())['path'] ?? "";
        $method = $req->getMethod();
        // handle HTTP OPTIONS requests
        if ($method === "OPTIONS") {
            return $this->handleHTTPOptions($target);
        }
        $func = $this->chooseCall($target, $method);
        if ($func === "opmlImport") {
            if (!HTTP::matchType($req, "", ...[self::ACCEPTED_TYPES_OPML])) {
                return new ErrorResponse(415, ['Accept' => implode(", ", self::ACCEPTED_TYPES_OPML)]);
            }
            $data = (string) $req->getBody();
        } elseif ($method === "POST" || $method === "PUT") {
            $data = @json_decode($data, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                // if the body could not be parsed as JSON, return "400 Bad Request"
                return new ErrorResponse(["invalidBodyJSON", json_last_error_msg()], 400);
            }
        } else {
            $data = null;
        }
        try {
            $path = explode("/", ltrim($target, "/"));
            return $this->$func($path, $req->getQueryParams(), $data);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            // if there was a REST exception return 400
            return new EmptyResponse(400);
        } catch (AbstractException $e) {
            // if there was any other Arsse exception return 500
            return new EmptyResponse(500);
        }
        // @codeCoverageIgnoreEnd
    }

    protected function normalizePathIds(string $url): string {
        $path = explode("/", $url);
        // any path components which are database IDs (integers greater than zero) should be replaced with "1", for easier comparison (we don't care about the specific ID)
        for ($a = 0; $a < sizeof($path); $a++) {
            if (ValueInfo::id($path[$a])) {
                $path[$a] = "1";
            }
        }
        // handle special case "Get User By User Name", which can have any non-numeric string, non-empty as the last component
        if (sizeof($path) === 3 && $path[0] === "" && $path[1] === "users" && !preg_match("/^(?:\d+)?$/", $path[2])) {
            $path[2] = "*";
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
                'Accept' => implode(", ", $url === "/import" ? self::ACCEPTED_TYPES_OPML : self::ACCEPTED_TYPES_JSON),
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
                // if it is allowed, return the object method to run, assuming the method exists
                if (method_exists($this, $this->paths[$url][$method])) {
                    return $this->paths[$url][$method];
                } else {
                    throw new Exception501(); // @codeCoverageIgnore
                }
            } else {
                // otherwise return 405
                throw new Exception405(implode(", ", array_keys($this->paths[$url])));
            }
        } else {
            // if the path is not supported, return 404
            throw new Exception404();
        }
    }

    public static function tokenGenerate(string $user, string $label): string {
        $t = base64_encode(random_bytes(24));
        return Arsse::$db->tokenCreate($user, "miniflux.login", $t, null, $label);
    }

    public static function tokenList(string $user): array {
        if (!Arsse::$db->userExists($user)) {
            throw new UserException("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $out = [];
        foreach (Arsse::$db->tokenList($user, "miniflux.login") as $r) {
            $out[] = ['label' => $r['data'], 'id' => $r['id']];
        }
        return $out;
    }
}
