<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\REST\Exception;
use JKingWeb\Arsse\User\ExceptionConflict as UserException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse as Response;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    public const VERSION = "2.0.25";

    protected const ACCEPTED_TYPES_OPML = ["application/xml", "text/xml", "text/x-opml"];
    protected const ACCEPTED_TYPES_JSON = ["application/json"];
    protected const TOKEN_LENGTH = 32;
    protected const VALID_JSON = [
        'url'        => "string",
        'username'   => "string",
        'password'   => "string",
        'user_agent' => "string",
        'title'      => "string",
    ];
    protected const CALLS = [                // handler method        Admin  Path   Body   Query
        '/categories'                    => [
            'GET'                        => ["getCategories",         false, false, false, false],
            'POST'                       => ["createCategory",        false, false, true,  false],
        ],
        '/categories/1'                  => [
            'PUT'                        => ["updateCategory",        false, true,  true,  false],
            'DELETE'                     => ["deleteCategory",        false, true,  false, false],
        ],
        '/categories/1/mark-all-as-read' => [
            'PUT'                        => ["markCategory",          false, true,  false, false],
        ],
        '/discover'                      => [
            'POST'                       => ["discoverSubscriptions", false, false, true,  false],
        ],
        '/entries'                       => [
            'GET'                        => ["getEntries",            false, false, false, true],
            'PUT'                        => ["updateEntries",         false, false, true,  false],
        ],
        '/entries/1'                     => [
            'GET'                        => ["getEntry",              false, true,  false, false],
        ],
        '/entries/1/bookmark'            => [
            'PUT'                        => ["toggleEntryBookmark",   false, true,  false, false],
        ],
        '/export'                        => [
            'GET'                        => ["opmlExport",            false, false, false, false],
        ],
        '/feeds'                         => [
            'GET'                        => ["getFeeds",              false, false, false, false],
            'POST'                       => ["createFeed",            false, false, true,  false],
        ],
        '/feeds/1'                       => [
            'GET'                        => ["getFeed",               false, true,  false, false],
            'PUT'                        => ["updateFeed",            false, true,  true,  false],
            'DELETE'                     => ["deleteFeed",            false, true,  false, false],
        ],
        '/feeds/1/entries'               => [
            'GET'                        => ["getFeedEntries",        false, true,  false, false],
        ],
        '/feeds/1/entries/1'             => [
            'GET'                        => ["getFeedEntry",          false, true,  false, false],
        ],
        '/feeds/1/icon'                  => [
            'GET'                        => ["getFeedIcon",           false, true,  false, false],
        ],
        '/feeds/1/mark-all-as-read'      => [
            'PUT'                        => ["markFeed",              false, true,  false, false],
        ],
        '/feeds/1/refresh'               => [
            'PUT'                        => ["refreshFeed",           false, true,  false, false],
        ],
        '/feeds/refresh'                 => [
            'PUT'                        => ["refreshAllFeeds",       false, false, false, false],
        ],
        '/import'                        => [
            'POST'                       => ["opmlImport",            false, false, true,  false],
        ],
        '/me'                            => [
            'GET'                        => ["getCurrentUser",        false, false, false, false],
        ],
        '/users'                         => [
            'GET'                        => ["getUsers",              true,  false, false, false],
            'POST'                       => ["createUser",            true,  false, true,  false],
        ],
        '/users/1'                       => [
            'GET'                        => ["getUserByNum",          true,  true,  false, false],
            'PUT'                        => ["updateUserByNum",       true,  true,  true,  false],
            'DELETE'                     => ["deleteUserByNum",       true,  true,  false, false],
        ],
        '/users/1/mark-all-as-read'      => [
            'PUT'                        => ["markUserByNum",         false, true,  false, false],
        ],
        '/users/*'                       => [
            'GET'                        => ["getUserById",           true,  true,  false, false],
        ],
    ];

    public function __construct() {
    }

    /** @codeCoverageIgnore */
    protected function now(): \DateTimeImmutable {
        return Date::normalize("now");
    }

    protected function authenticate(ServerRequestInterface $req): bool {
        // first check any tokens; this is what Miniflux does
        if ($req->hasHeader("X-Auth-Token")) {
            $t = $req->getHeader("X-Auth-Token")[0]; // consider only the first token
            if (strlen($t)) { // and only if it is not blank
                try {
                    $d = Arsse::$db->tokenLookup("miniflux.login", $t);
                } catch (ExceptionInput $e) {
                    return false;
                }
                Arsse::$user->id = $d['user'];
                return true;
            }
        }
        // next check HTTP auth 
        if ($req->getAttribute("authenticated", false)) {
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
            return true;
        }
        return false;
    }

    protected function isAdmin(): bool {
        return (bool) Arsse::$user->propertiesGet(Arsse::$user->id, false)['admin'];
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
        if ($func instanceof ResponseInterface) {
            return $func;
        } else {
            [$func, $reqAdmin, $reqPath, $reqBody, $reqQuery] = $func;
        }
        if ($reqAdmin && !$this->isAdmin()) {
            return new ErrorResponse("403", 403);
        }
        $args = [];
        if ($reqPath) {
            $args[] = explode("/", ltrim($target, "/"));
        }
        if ($reqBody) {
            if ($func === "opmlImport") {
                if (!HTTP::matchType($req, "", ...[self::ACCEPTED_TYPES_OPML])) {
                    return new ErrorResponse("", 415, ['Accept' => implode(", ", self::ACCEPTED_TYPES_OPML)]);
                }
                $args[] = (string) $req->getBody();
            } else {
                $data = (string) $req->getBody();
                if (strlen($data)) {
                    $data = @json_decode($data, true);
                    if (json_last_error() !== \JSON_ERROR_NONE) {
                        // if the body could not be parsed as JSON, return "400 Bad Request"
                        return new ErrorResponse(["InvalidBodyJSON", json_last_error_msg()], 400);
                    }
                } else {
                    $data = [];
                }
                $data = $this->normalizeBody((array) $data);
                if ($data instanceof ResponseInterface) {
                    return $data;
                }
            }
            $args[] = $data;
        }
        if ($reqQuery) {
            $args[] = $req->getQueryParams();
        }
        try {
            return $this->$func(...$args);
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

    protected function chooseCall(string $url, string $method) {
        // // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIds($url);
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // we now evaluate the supplied URL against every supported path for the selected scope
        if (isset(self::CALLS[$url])) {
            // if the path is supported, make sure the method is allowed
            if (isset(self::CALLS[$url][$method])) {
                // if it is allowed, return the object method to run, assuming the method exists
                assert(method_exists($this, self::CALLS[$url][$method][0]), new \Exception("Method is not implemented"));
                return self::CALLS[$url][$method];
            } else {
                // otherwise return 405
                return new EmptyResponse(405, ['Allow' => implode(", ", array_keys(self::CALLS[$url]))]);
            }
        } else {
            // if the path is not supported, return 404
            return new EmptyResponse(404);
        }
    }

    protected function normalizePathIds(string $url): string {
        $path = explode("/", $url);
        // any path components which are database IDs (integers greater than zero) should be replaced with "1", for easier comparison (we don't care about the specific ID)
        for ($a = 0; $a < sizeof($path); $a++) {
            if (V::id($path[$a])) {
                $path[$a] = "1";
            }
        }
        // handle special case "Get User By User Name", which can have any non-numeric string, non-empty as the last component
        if (sizeof($path) === 3 && $path[0] === "" && $path[1] === "users" && !preg_match("/^(?:\d+)?$/", $path[2])) {
            $path[2] = "*";
        }
        return implode("/", $path);
    }

    protected function normalizeBody(array $body) {
        // Miniflux does not attempt to coerce values into different types
        foreach (self::VALID_JSON as $k => $t) {
            if (!isset($body[$k])) {
                $body[$k] = null;
            } elseif (gettype($body[$k]) !== $t) {
                return new ErrorResponse(["InvalidInputType", 'field' => $k, 'expected' => $t, 'actual' => gettype($body[$k])]);
            }
        }
        return $body;
    }

    protected function handleHTTPOptions(string $url): ResponseInterface {
        // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIDs($url);
        if (isset(self::CALLS[$url])) {
            // if the path is supported, respond with the allowed methods and other metadata
            $allowed = array_keys(self::CALLS[$url]);
            // if GET is allowed, so is HEAD
            if (in_array("GET", $allowed)) {
                array_unshift($allowed, "HEAD");
            }
            return new EmptyResponse(204, [
                'Allow'  => implode(", ", $allowed),
                'Accept' => implode(", ", $url === "/import" ? self::ACCEPTED_TYPES_OPML : self::ACCEPTED_TYPES_JSON),
            ]);
        } else {
            // if the path is not supported, return 404
            return new EmptyResponse(404);
        }
    }

    protected function listUsers(array $users, bool $reportMissing): array {
        $out = [];
        $now = Date::transform($this->now(), "iso8601m");
        foreach ($users as $u) {
            try {
                $info = Arsse::$user->propertiesGet($u, true);
            } catch (UserException $e) {
                if ($reportMissing) {
                    throw $e;
                } else {
                    continue;
                }
            }
            $out[] = [
                'id'                      => $info['num'],
                'username'                => $u,
                'is_admin'                => $info['admin'] ?? false,
                'theme'                   => $info['theme'] ?? "light_serif",
                'language'                => $info['lang'] ?? "en_US",
                'timezone'                => $info['tz'] ?? "UTC",
                'entry_sorting_direction' => ($info['sort_asc'] ?? false) ? "asc" : "desc",
                'entries_per_page'        => $info['page_size'] ?? 100,
                'keyboard_shortcuts'      => $info['shortcuts'] ?? true,
                'show_reading_time'       => $info['reading_time'] ?? true,
                'last_login_at'           => $now,
                'entry_swipe'             => $info['swipe'] ?? true,
                'extra'                   => [
                    'custom_css' => $info['stylesheet'] ?? "",
                ],
            ];
        }
        return $out;
    }

    protected function discoverSubscriptions(array $data): ResponseInterface {
        try {
            $list = Feed::discoverAll((string) $data['url'], (string) $data['username'], (string) $data['password']);
        } catch (FeedException $e) {
            $msg = [
                10502 => "Fetch404",
                10506 => "Fetch403",
                10507 => "Fetch401",
            ][$e->getCode()] ?? "FetchOther";
            return new ErrorResponse($msg, 500);
        }
        $out = [];
        foreach($list as $url) {
            // TODO: This needs to be refined once PicoFeed is replaced
            $out[] = ['title' => "Feed", 'type' => "rss", 'url' => $url];
        }
        return new Response($out);
    }

    protected function getUsers(): ResponseInterface {
        return new Response($this->listUsers(Arsse::$user->list(), false));
    }

    protected function getUserById(array $path): ResponseInterface {
        try {
            return new Response($this->listUsers([$path[1]], true)[0] ?? new \stdClass);
        } catch (UserException $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getUserByNum(array $path): ResponseInterface {
        try {
            $user = Arsse::$user->lookup((int) $path[1]);
            return new Response($this->listUsers([$user], true)[0] ?? new \stdClass);
        } catch (UserException $e) {
            return new ErrorResponse("404", 404);
        }
    }
    
    protected function getCurrentUser(): ResponseInterface {
        return new Response($this->listUsers([Arsse::$user->id], false)[0] ?? new \stdClass);
    }

    protected function getCategories(): ResponseInterface {
        $out = [];
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        // add the root folder as a category
        $out[] = ['id' => 1, 'title' => $meta['root_folder_name'] ?? Arsse::$lang->msg("API.Miniflux.DefaultCategoryName"), 'user_id' => $meta['num']];
        // add other top folders as categories
        foreach (Arsse::$db->folderList(Arsse::$user->id, null, false) as $f) {
            // always add 1 to the ID since the root folder will always be 1 instead of 0.
            $out[] = ['id' => $f['id'] + 1, 'title' => $f['name'], 'user_id' => $meta['num']];
        }
        return new Response($out);
    }

    protected function createCategory(array $data): ResponseInterface {
        try {
            $id = Arsse::$db->folderAdd(Arsse::$user->id, ['name' => (string) $data['title']]);
        } catch (ExceptionInput $e) {
            if ($e->getCode() === 10236) {
                return new ErrorResponse(["DuplicateCategory", 'title' => $data['title']], 500);
            } else {
                return new ErrorResponse(["InvalidCategory", 'title' => $data['title']], 500);
            }
        }
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        return new Response(['id' => $id + 1, 'title' => $data['title'], 'user_id' => $meta['num']]);
    }

    protected function updateCategory(array $path, array $data): ResponseInterface {
        // category IDs in Miniflux are always greater than 1; we have folder 0, so we decrement category IDs by 1 to get the folder ID
        $folder = $path[1] - 1;
        $title = $data['title'] ?? "";
        try {
            if ($folder === 0) {
                // folder 0 doesn't actually exist in the database, so its name is kept as user metadata
                if (!strlen(trim($title))) {
                    throw new ExceptionInput("whitespace");
                }
                $title = Arsse::$user->propertiesSet(Arsse::$user->id, ['root_folder_name' => $title])['root_folder_name'];
            } else {
                Arsse::$db->folderPropertiesSet(Arsse::$user->id, $folder, ['name' => $title]);
            }
        } catch (ExceptionInput $e) {
            if ($e->getCode() === 10236) {
                return new ErrorResponse(["DuplicateCategory", 'title' => $title], 500);
            } elseif (in_array($e->getCode(), [10237, 10239])) {
                return new ErrorResponse("404", 404);
            } else {
                return new ErrorResponse(["InvalidCategory", 'title' => $title], 500);
            }
        }
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        return new Response(['id' => (int) $path[1], 'title' => $title, 'user_id' => $meta['num']]);
    }

    protected function deleteCategory(array $path): ResponseInterface {
        try {
            $folder = $path[1] - 1;
            if ($folder !== 0) {
                Arsse::$db->folderRemove(Arsse::$user->id, $folder);
            } else {
                // if we're deleting from the root folder, delete each child subscription individually
                // otherwise we'd be deleting the entire tree 
                $tr = Arsse::$db->begin();
                foreach (Arsse::$db->subscriptionList(Arsse::$user->id, null, false) as $sub) {
                    Arsse::$db->subscriptionRemove(Arsse::$user->id, $sub['id']);
                }
                $tr->commit();
            }
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    protected function markCategory(array $path): ResponseInterface {
        $folder = $path[1] - 1;
        $c = new Context;
        if ($folder === 0) {
            // if we're marking the root folder don't also mark its child folders, since Miniflux organizes it as a peer of other folders
            $c = $c->folderShallow($folder);
        } else {
            $c = $c->folder($folder);
        }
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    public static function tokenGenerate(string $user, string $label): string {
        // Miniflux produces tokens in base64url alphabet
        $t = str_replace(["+", "/"], ["-", "_"], base64_encode(random_bytes(self::TOKEN_LENGTH)));
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
