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
    protected const PATHS = [
        '/categories'                    => ['GET'  => "getCategories",  'POST'   => "createCategory"],
        '/categories/1'                  => ['PUT'  => "updateCategory", 'DELETE' => "deleteCategory"],
        '/categories/1/mark-all-as-read' => ['PUT'  => "markCategory"],
        '/discover'                      => ['POST' => "discoverSubscriptions"],
        '/entries'                       => ['GET'  => "getEntries",     'PUT'    => "updateEntries"],
        '/entries/1'                     => ['GET'  => "getEntry"],
        '/entries/1/bookmark'            => ['PUT'  => "toggleEntryBookmark"],
        '/export'                        => ['GET'  => "opmlExport"],
        '/feeds'                         => ['GET'  => "getFeeds",       'POST'   => "createFeed"],
        '/feeds/1'                       => ['GET'  => "getFeed",        'PUT'    => "updateFeed",    'DELETE' => "removeFeed"],
        '/feeds/1/mark-all-as-read'      => ['PUT'  => "markFeed"],
        '/feeds/1/entries/1'             => ['GET'  => "getFeedEntry"],
        '/feeds/1/entries'               => ['GET'  => "getFeedEntries"],
        '/feeds/1/icon'                  => ['GET'  => "getFeedIcon"],
        '/feeds/1/refresh'               => ['PUT'  => "refreshFeed"],
        '/feeds/refresh'                 => ['PUT'  => "refreshAllFeeds"],
        '/import'                        => ['POST' => "opmlImport"],
        '/me'                            => ['GET'  => "getCurrentUser"],
        '/users'                         => ['GET'  => "getUsers",       'POST' => "createUser"],
        '/users/1'                       => ['GET'  => "getUserByNum",   'PUT'  => "updateUserByNum", 'DELETE' => "deleteUser"],
        '/users/1/mark-all-as-read'      => ['PUT'  => "markAll"],
        '/users/*'                       => ['GET'  => "getUserById"],
    ];
    protected const ADMIN_FUNCTIONS = [
        'getUsers'        => true, 
        'getUserByNum'    => true, 
        'getUserById'     => true, 
        'createUser'      => true, 
        'updateUserByNum' => true, 
        'deleteUser'      => true,
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
        }
        if ((self::ADMIN_FUNCTIONS[$func] ?? false) && !$this->isAdmin()) {
            return new ErrorResponse("403", 403);
        }
        $data = [];
        $query = [];
        if ($func === "opmlImport") {
            if (!HTTP::matchType($req, "", ...[self::ACCEPTED_TYPES_OPML])) {
                return new ErrorResponse("", 415, ['Accept' => implode(", ", self::ACCEPTED_TYPES_OPML)]);
            }
            $data = (string) $req->getBody();
        } elseif ($method === "POST" || $method === "PUT") {
            $data = @json_decode((string) $req->getBody(), true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                // if the body could not be parsed as JSON, return "400 Bad Request"
                return new ErrorResponse(["InvalidBodyJSON", json_last_error_msg()], 400);
            }
            $data = $this->normalizeBody((array) $data);
            if ($data instanceof ResponseInterface) {
                return $data;
            }
        } elseif ($method === "GET") {
            $query = $req->getQueryParams();
        }
        try {
            $path = explode("/", ltrim($target, "/"));
            return $this->$func($path, $query, $data);
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

    protected function handleHTTPOptions(string $url): ResponseInterface {
        // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIDs($url);
        if (isset(self::PATHS[$url])) {
            // if the path is supported, respond with the allowed methods and other metadata
            $allowed = array_keys(self::PATHS[$url]);
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

    protected function chooseCall(string $url, string $method) {
        // // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIds($url);
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // we now evaluate the supplied URL against every supported path for the selected scope
        // the URL is evaluated as an array so as to avoid decoded escapes turning invalid URLs into valid ones
        if (isset(self::PATHS[$url])) {
            // if the path is supported, make sure the method is allowed
            if (isset(self::PATHS[$url][$method])) {
                // if it is allowed, return the object method to run, assuming the method exists
                assert(method_exists($this, self::PATHS[$url][$method]), new \Exception("Method is not implemented"));
                return self::PATHS[$url][$method];
            } else {
                // otherwise return 405
                return new EmptyResponse(405, ['Allow' => implode(", ", array_keys(self::PATHS[$url]))]);
            }
        } else {
            // if the path is not supported, return 404
            return new EmptyResponse(404);
        }
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

    protected function discoverSubscriptions(array $path, array $query, array $data): ResponseInterface {
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

    protected function getUsers(array $path, array $query, array $data): ResponseInterface {
        return new Response($this->listUsers(Arsse::$user->list(), false));
    }

    protected function getUserById(array $path, array $query, array $data): ResponseInterface {
        try {
            return new Response($this->listUsers([$path[1]], true)[0] ?? new \stdClass);
        } catch (UserException $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getUserByNum(array $path, array $query, array $data): ResponseInterface {
        try {
            $user = Arsse::$user->lookup((int) $path[1]);
            return new Response($this->listUsers([$user], true)[0] ?? new \stdClass);
        } catch (UserException $e) {
            return new ErrorResponse("404", 404);
        }
    }
    
    protected function getCurrentUser(array $path, array $query, array $data): ResponseInterface {
        return new Response($this->listUsers([Arsse::$user->id], false)[0] ?? new \stdClass);
    }

    protected function getCategories(array $path, array $query, array $data): ResponseInterface {
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

    protected function createCategory(array $path, array $query, array $data): ResponseInterface {
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

    protected function updateCategory(array $path, array $query, array $data): ResponseInterface {
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
            } elseif ($e->getCode() === 10239) {
                return new ErrorResponse("404", 404);
            } else {
                return new ErrorResponse(["InvalidCategory", 'title' => $title], 500);
            }
        }
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        return new Response(['id' => (int) $path[1], 'title' => $title, 'user_id' => $meta['num']]);
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
