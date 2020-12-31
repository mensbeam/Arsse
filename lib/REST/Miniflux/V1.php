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
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\Exception as UserException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse as Response;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    public const VERSION = "2.0.26";

    protected const ACCEPTED_TYPES_OPML = ["application/xml", "text/xml", "text/x-opml"];
    protected const ACCEPTED_TYPES_JSON = ["application/json"];
    protected const TOKEN_LENGTH = 32;
    protected const VALID_JSON = [
        // user properties which map directly to Arsse user metadata are listed separately
        'url'        => "string",
        'username'   => "string",
        'password'   => "string",
        'user_agent' => "string",
        'title'      => "string",
    ];
    protected const USER_META_MAP = [
        // Miniflux ID             // Arsse ID        Default value  Extra
        'is_admin'                => ["admin",        false,         false],
        'theme'                   => ["theme",        "light_serif", false],
        'language'                => ["lang",         "en_US",       false],
        'timezone'                => ["tz",           "UTC",         false],
        'entry_sorting_direction' => ["sort_asc",     false,         false],
        'entries_per_page'        => ["page_size",    100,           false],
        'keyboard_shortcuts'      => ["shortcuts",    true,          false],
        'show_reading_time'       => ["reading_time", true,          false],
        'entry_swipe'             => ["swipe",        true,          false],
        'custom_css'              => ["stylesheet",   "",            true],
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
            'PUT'                        => ["updateUserByNum",       false, true,  true,  false], // requires admin for users other than self
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
                return new ErrorResponse(["InvalidInputType", 'field' => $k, 'expected' => $t, 'actual' => gettype($body[$k])], 422);
            }
        }
        foreach (self::USER_META_MAP as $k => [,$d,]) {
            $t = gettype($d);
            if (!isset($body[$k])) {
                $body[$k] = null;
            } elseif ($k === "entry_sorting_direction") {
                if (!in_array($body[$k], ["asc", "desc"])) {
                    return new ErrorResponse(["InvalidInputValue", 'field' => $k], 422);
                }
            } elseif (gettype($body[$k]) !== $t) {
                return new ErrorResponse(["InvalidInputType", 'field' => $k, 'expected' => $t, 'actual' => gettype($body[$k])], 422);
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
            $entry = [
                'id'                      => $info['num'],
                'username'                => $u,
                'last_login_at'           => $now,
            ];
            foreach (self::USER_META_MAP as $ext => [$int, $default, $extra]) {
                if (!$extra) {
                    $entry[$ext] = $info[$int] ?? $default;
                } else {
                    if (!isset($entry['extra'])) {
                        $entry['extra'] = [];
                    }
                    $entry['extra'][$ext] = $info[$int] ?? $default;
                }
            }
            $entry['entry_sorting_direction'] = ($entry['entry_sorting_direction']) ? "asc" : "desc";
            $out[] = $entry;
        }
        return $out;
    }

    protected function editUser(string $user, array $data): array {
        // map Miniflux properties to internal metadata properties
        $in = [];
        foreach (self::USER_META_MAP as $i => [$o,,]) {
            if (isset($data[$i])) {
                if ($i === "entry_sorting_direction") {
                    $in[$o] = $data[$i] === "asc";
                } else {
                    $in[$o] = $data[$i];
                }
            }
        }
        // make any requested changes
        $tr = Arsse::$user->begin();
        if ($in) {
            Arsse::$user->propertiesSet($user, $in);
        }
        // read out the newly-modified user and commit the changes
        $out = $this->listUsers([$user], true)[0];
        $tr->commit();
        // add the input password if a password change was requested
        if (isset($data['password'])) {
            $out['password'] = $data['password'];
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
        foreach ($list as $url) {
            // TODO: This needs to be refined once PicoFeed is replaced
            $out[] = ['title' => "Feed", 'type' => "rss", 'url' => $url];
        }
        return new Response($out);
    }

    protected function getUsers(): ResponseInterface {
        $tr = Arsse::$user->begin();
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

    protected function createUser(array $data): ResponseInterface {
        if ($data['username'] === null) {
            return new ErrorResponse(["MissingInputValue", 'field' => "username"], 422);
        } elseif ($data['password'] === null) {
            return new ErrorResponse(["MissingInputValue", 'field' => "password"], 422);
        }
        try {
            $tr = Arsse::$user->begin();
            $data['password'] = Arsse::$user->add($data['username'], $data['password']);
            $out = $this->editUser($data['username'], $data);
            $tr->commit();
        } catch (UserException $e) {
            switch ($e->getCode()) {
                case 10403:
                    return new ErrorResponse(["DuplicateUser", 'user' => $data['username']], 409);
                case 10441:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "timezone"], 422);
                case 10443:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "entries_per_page"], 422);
                case 10444:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "username"], 422);
            }
            throw $e; // @codeCoverageIgnore
        }
        return new Response($out, 201);
    }

    protected function updateUserByNum(array $path, array $data): ResponseInterface {
        // this function is restricted to admins unless the affected user and calling user are the same
        $user = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        if (((int) $path[1]) === $user['num']) {
            if ($data['is_admin'] && !$user['admin']) {
                // non-admins should not be able to set themselves as admin
                return new ErrorResponse("InvalidElevation", 403);
            }
            $user = Arsse::$user->id;
        } elseif (!$user['admin']) {
            return new ErrorResponse("403", 403);
        } else {
            try {
                $user = Arsse::$user->lookup((int) $path[1]);
            } catch (ExceptionConflict $e) {
                return new ErrorResponse("404", 404);
            }
        }
        // make any requested changes
        try {
            $tr = Arsse::$user->begin();
            if (isset($data['username'])) {
                Arsse::$user->rename($user, $data['username']);
                $user = $data['username'];
            }
            if (isset($data['password'])) {
                Arsse::$user->passwordSet($user, $data['password']);
            }
            $out = $this->editUser($user, $data);
            $tr->commit();
        } catch (UserException $e) {
            switch ($e->getCode()) {
                case 10403:
                    return new ErrorResponse(["DuplicateUser", 'user' => $data['username']], 409);
                case 10441:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "timezone"], 422);
                case 10443:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "entries_per_page"], 422);
                case 10444:
                    return new ErrorResponse(["InvalidInputValue", 'field' => "username"], 422);
            }
            throw $e; // @codeCoverageIgnore
        }
        return new Response($out);
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
        return new Response(['id' => $id + 1, 'title' => $data['title'], 'user_id' => $meta['num']], 201);
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
            throw new ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $out = [];
        foreach (Arsse::$db->tokenList($user, "miniflux.login") as $r) {
            $out[] = ['label' => $r['data'], 'id' => $r['id']];
        }
        return $out;
    }
}
