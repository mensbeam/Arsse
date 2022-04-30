<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\ExceptionType;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\Context\RootContext;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\ImportExport\Exception as ImportException;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\URL;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\REST\Exception;
use JKingWeb\Arsse\Rule\Rule;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\Exception as UserException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse as Response;
use Laminas\Diactoros\Response\TextResponse as GenericResponse;
use Laminas\Diactoros\Uri;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    public const VERSION = "2.0.28";

    protected const ACCEPTED_TYPES_OPML = ["application/xml", "text/xml", "text/x-opml"];
    protected const ACCEPTED_TYPES_JSON = ["application/json"];
    protected const DEFAULT_ENTRY_LIMIT = 100;
    protected const DEFAULT_ORDER_COL = "modified_date";
    protected const DATE_FORMAT_SEC = "Y-m-d\TH:i:sP";
    protected const DATE_FORMAT_MICRO = "Y-m-d\TH:i:s.uP";
    protected const VALID_QUERY = [
        'status'          => V::T_STRING + V::M_ARRAY,
        'offset'          => V::T_INT,
        'limit'           => V::T_INT,
        'order'           => V::T_STRING,
        'direction'       => V::T_STRING,
        'before'          => V::T_DATE, // Unix timestamp
        'after'           => V::T_DATE, // Unix timestamp
        'before_entry_id' => V::T_INT,
        'after_entry_id'  => V::T_INT,
        'starred'         => V::T_MIXED, // the presence of the starred key is the only thing considered by Miniflux
        'search'          => V::T_STRING,
        'category_id'     => V::T_INT,
    ];
    protected const VALID_JSON = [
        // user properties which map directly to Arsse user metadata are listed separately;
        // not all these properties are used by our implementation, but they are treated
        // with the same strictness as in Miniflux to ease cross-compatibility
        'url'               => "string",
        'username'          => "string",
        'password'          => "string",
        'user_agent'        => "string",
        'title'             => "string",
        'feed_url'          => "string",
        'category_id'       => "integer",
        'crawler'           => "boolean",
        'user_agent'        => "string",
        'scraper_rules'     => "string",
        'rewrite_rules'     => "string",
        'keeplist_rules'    => "string",
        'blocklist_rules'   => "string",
        'disabled'          => "boolean",
        'ignore_http_cache' => "boolean",
        'fetch_via_proxy'   => "boolean",
        'entry_ids'         => "array", // this is a special case: it is an array of integers
        'status'            => "string",
    ];
    protected const USER_META_MAP = [
        // Miniflux ID             // Arsse ID        Default value
        'is_admin'                => ["admin",        false],
        'theme'                   => ["theme",        "light_serif"],
        'language'                => ["lang",         "en_US"],
        'timezone'                => ["tz",           "UTC"],
        'entry_sorting_direction' => ["sort_asc",     false],
        'entries_per_page'        => ["page_size",    100],
        'keyboard_shortcuts'      => ["shortcuts",    true],
        'show_reading_time'       => ["reading_time", true],
        'entry_swipe'             => ["swipe",        true],
        'stylesheet'              => ["stylesheet",   ""],
    ];
    /** A map between Miniflux's input properties and our input properties when modifiying feeds
     *
     * Miniflux also allows changing the following properties:
     *
     *  - feed_url
     *  - username
     *  - password
     *  - user_agent
     *  - scraper_rules
     *  - rewrite_rules
     *  - disabled
     *  - ignore_http_cache
     *  - fetch_via_proxy
     *
     *  These either do not apply because we have no cache or proxy,
     *  or cannot be changed because feeds are deduplicated and changing
     *  how they are fetched is not practical with our implementation.
     *  The properties are still checked for type and syntactic validity
     *  where practical, on the assumption Miniflux would also reject
     *  invalid values.
     */
    protected const FEED_META_MAP = [
        'title'           => "title",
        'category_id'     => "folder",
        'crawler'         => "scrape",
        'keeplist_rules'  => "keep_rule",
        'blocklist_rules' => "block_rule",
    ];
    protected const ARTICLE_COLUMNS = [
        "id", "url", "title", "subscription",
        "author", "fingerprint",
        "published_date", "modified_date",
        "starred", "unread", "hidden",
        "content", "media_url", "media_type",
    ];
    protected const CALLS = [                // handler method        Admin  Path   Body   Query  Required fields
        '/categories'                    => [
            'GET'                        => ["getCategories",         false, false, false, false, []],
            'POST'                       => ["createCategory",        false, false, true,  false, ["title"]],
        ],
        '/categories/1'                  => [
            'PUT'                        => ["updateCategory",        false, true,  true,  false, ["title"]], // title is effectively required since no other field can be changed
            'DELETE'                     => ["deleteCategory",        false, true,  false, false, []],
        ],
        '/categories/1/entries'          => [
            'GET'                        => ["getCategoryEntries",    false, true,  false, true,  []],
        ],
        '/categories/1/entries/1'        => [
            'GET'                        => ["getCategoryEntry",      false, true,  false, false, []],
        ],
        '/categories/1/feeds'            => [
            'GET'                        => ["getCategoryFeeds",      false, true,  false, false, []],
        ],
        '/categories/1/mark-all-as-read' => [
            'PUT'                        => ["markCategory",          false, true,  false, false, []],
        ],
        '/discover'                      => [
            'POST'                       => ["discoverSubscriptions", false, false, true,  false, ["url"]],
        ],
        '/entries'                       => [
            'GET'                        => ["getEntries",            false, false, false, true,  []],
            'PUT'                        => ["updateEntries",         false, false, true,  false, ["entry_ids", "status"]],
        ],
        '/entries/1'                     => [
            'GET'                        => ["getEntry",              false, true,  false, false, []],
        ],
        '/entries/1/bookmark'            => [
            'PUT'                        => ["toggleEntryBookmark",   false, true,  false, false, []],
        ],
        '/export'                        => [
            'GET'                        => ["opmlExport",            false, false, false, false, []],
        ],
        '/feeds'                         => [
            'GET'                        => ["getFeeds",              false, false, false, false, []],
            'POST'                       => ["createFeed",            false, false, true,  false, ["feed_url", "category_id"]],
        ],
        '/feeds/1'                       => [
            'GET'                        => ["getFeed",               false, true,  false, false, []],
            'PUT'                        => ["updateFeed",            false, true,  true,  false, []],
            'DELETE'                     => ["deleteFeed",            false, true,  false, false, []],
        ],
        '/feeds/1/entries'               => [
            'GET'                        => ["getFeedEntries",        false, true,  false, true,  []],
        ],
        '/feeds/1/entries/1'             => [
            'GET'                        => ["getFeedEntry",          false, true,  false, false, []],
        ],
        '/feeds/1/icon'                  => [
            'GET'                        => ["getFeedIcon",           false, true,  false, false, []],
        ],
        '/feeds/1/mark-all-as-read'      => [
            'PUT'                        => ["markFeed",              false, true,  false, false, []],
        ],
        '/feeds/1/refresh'               => [
            'PUT'                        => ["refreshFeed",           false, true,  false, false, []],
        ],
        '/feeds/refresh'                 => [
            'PUT'                        => ["refreshAllFeeds",       false, false, false, false, []],
        ],
        '/import'                        => [
            'POST'                       => ["opmlImport",            false, false, true,  false, []],
        ],
        '/me'                            => [
            'GET'                        => ["getCurrentUser",        false, false, false, false, []],
        ],
        '/users'                         => [
            'GET'                        => ["getUsers",              true,  false, false, false, []],
            'POST'                       => ["createUser",            true,  false, true,  false, ["username", "password"]],
        ],
        '/users/1'                       => [
            'GET'                        => ["getUserByNum",          true,  true,  false, false, []],
            'PUT'                        => ["updateUserByNum",       false, true,  true,  false, []], // requires admin for users other than self
            'DELETE'                     => ["deleteUserByNum",       true,  true,  false, false, []],
        ],
        '/users/1/mark-all-as-read'      => [
            'PUT'                        => ["markUserByNum",         false, true,  false, false, []],
        ],
        '/users/*'                       => [
            'GET'                        => ["getUserById",           true,  true,  false, false, []],
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
        // get the request path only; this is assumed to already be normalized
        $target = parse_url($req->getRequestTarget(), \PHP_URL_PATH) ?? "";
        $method = $req->getMethod();
        // handle HTTP OPTIONS requests
        if ($method === "OPTIONS") {
            return $this->handleHTTPOptions($target);
        }
        // try to authenticate
        if (!$this->authenticate($req)) {
            return new ErrorResponse("401", 401);
        }
        $func = $this->chooseCall($target, $method);
        if ($func instanceof ResponseInterface) {
            return $func;
        } else {
            [$func, $reqAdmin, $reqPath, $reqBody, $reqQuery, $reqFields] = $func;
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
                $data = (string) $req->getBody();
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
                $data = $this->normalizeBody((array) $data, $reqFields);
                if ($data instanceof ResponseInterface) {
                    return $data;
                }
            }
            $args[] = $data;
        }
        if ($reqQuery) {
            $query = $this->normalizeQuery(parse_url($req->getRequestTarget(), \PHP_URL_QUERY) ?? "");
            if ($query instanceof ResponseInterface) {
                return $query;
            }
            $args[] = $query;
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
        if (sizeof($path) === 3 && $path[0] === "" && $path[1] === "users" && !preg_match("/^(?:\d+)?$/D", $path[2])) {
            $path[2] = "*";
        }
        return implode("/", $path);
    }

    protected function normalizeBody(array $body, array $req) {
        // Miniflux does not attempt to coerce values into different types
        foreach (self::VALID_JSON as $k => $t) {
            if (!isset($body[$k])) {
                $body[$k] = null;
            } elseif (gettype($body[$k]) !== $t) {
                return new ErrorResponse(["InvalidInputType", 'field' => $k, 'expected' => $t, 'actual' => gettype($body[$k])], 422);
            } elseif (
                (in_array($k, ["keeplist_rules", "blocklist_rules"]) && !Rule::validate($body[$k]))
                || (in_array($k, ["url", "feed_url"]) && !URL::absolute($body[$k]))
                || ($k === "category_id" && $body[$k] < 1)
                || ($k === "status" && !in_array($body[$k], ["read", "unread", "removed"]))
            ) {
                return new ErrorResponse(["InvalidInputValue", 'field' => $k], 422);
            } elseif ($k === "entry_ids") {
                foreach ($body[$k] as $v) {
                    if (gettype($v) !== "integer") {
                        return new ErrorResponse(["InvalidInputType", 'field' => $k, 'expected' => "integer", 'actual' => gettype($v)], 422);
                    } elseif ($v < 1) {
                        return new ErrorResponse(["InvalidInputValue", 'field' => $k], 422);
                    }
                }
            }
        }
        //normalize user-specific input
        foreach (self::USER_META_MAP as $k => [,$d]) {
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
        // check for any missing required values
        foreach ($req as $k) {
            if (!isset($body[$k]) || (is_array($body[$k]) && !$body[$k])) {
                return new ErrorResponse(["MissingInputValue", 'field' => $k], 422);
            }
        }
        return $body;
    }

    protected function normalizeQuery(string $query) {
        // fill an array with all valid keys
        $out = [];
        $seen = [];
        foreach (self::VALID_QUERY as $k => $t) {
            $out[$k] = ($t >= V::M_ARRAY) ? [] : null;
            $seen[$k] = false;
        }
        // split the query string and normalize the values to their correct types
        foreach (explode("&", $query) as $parts) {
            $parts = explode("=", $parts, 2);
            $k = rawurldecode($parts[0]);
            $v = (isset($parts[1])) ? rawurldecode($parts[1]) : "";
            if (!isset(self::VALID_QUERY[$k])) {
                // ignore unknown keys
                continue;
            }
            $t = self::VALID_QUERY[$k] & ~V::M_ARRAY;
            $a = self::VALID_QUERY[$k] >= V::M_ARRAY;
            try {
                if ($seen[$k] && !$a) {
                    // if the key has already been seen and it's not an array field, bail
                    // NOTE: Miniflux itself simply ignores duplicates entirely
                    return new ErrorResponse(["DuplicateInputValue", 'field' => $k], 400);
                }
                $seen[$k] = true;
                if ($k === "starred") {
                    // the starred key is a special case in that Miniflux only considers the presence of the key
                    $out[$k] = true;
                    continue;
                } elseif ($v === "") {
                    // if the value is empty we can discard the value, but subsequent values for the same non-array key are still considered duplicates
                    continue;
                } elseif ($a) {
                    $out[$k][] = V::normalize($v, $t + V::M_STRICT, "unix");
                } else {
                    $out[$k] = V::normalize($v, $t + V::M_STRICT, "unix");
                }
            } catch (ExceptionType $e) {
                return new ErrorResponse(["InvalidInputValue", 'field' => $k], 400);
            }
            // perform additional validation
            if (
                (in_array($k, ["category_id", "before_entry_id", "after_entry_id"]) && $v < 1)
                || (in_array($k, ["limit", "offset"]) && $v < 0)
                || ($k === "direction" && !in_array($v, ["asc", "desc"]))
                || ($k === "order" && !in_array($v, ["id", "status", "published_at", "category_title", "category_id"]))
                || ($k === "status" && !in_array($v, ["read", "unread", "removed"]))
            ) {
                return new ErrorResponse(["InvalidInputValue", 'field' => $k], 400);
            }
        }
        return $out;
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
                'google_id'               => "",
                'openid_connect_id'       => "",
            ];
            foreach (self::USER_META_MAP as $ext => [$int, $default]) {
                $entry[$ext] = $info[$int] ?? $default;
            }
            $entry['entry_sorting_direction'] = ($entry['entry_sorting_direction']) ? "asc" : "desc";
            $out[] = $entry;
        }
        return $out;
    }

    protected function editUser(string $user, array $data): array {
        // map Miniflux properties to internal metadata properties
        $in = [];
        foreach (self::USER_META_MAP as $i => [$o]) {
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
                10521 => "Fetch404",
            ][$e->getCode()] ?? "FetchOther";
            return new ErrorResponse($msg, 502);
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
        return new Response($out, 201);
    }

    protected function deleteUserByNum(array $path): ResponseInterface {
        try {
            Arsse::$user->remove(Arsse::$user->lookup((int) $path[1]));
        } catch (ExceptionConflict $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    /** Returns a useful subset of user metadata
     *
     * The following keys are included:
     *
     * - "num": The user's numeric ID,
     * - "root": The effective name of the root folder
     * - "tz": The time zone preference of the user, or UTC if not set
     */
    protected function userMeta(string $user): array {
        $meta = Arsse::$user->propertiesGet($user, false);
        return [
            'num'  => $meta['num'],
            'root' => $meta['root_folder_name'] ?? Arsse::$lang->msg("API.Miniflux.DefaultCategoryName"),
            'tz'   => new \DateTimeZone($meta['tz'] ?? "UTC"),
        ];
    }

    protected function getCategories(): ResponseInterface {
        $out = [];
        // add the root folder as a category
        $meta = $this->userMeta(Arsse::$user->id);
        $out[] = ['id' => 1, 'title' => $meta['root'], 'user_id' => $meta['num']];
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
                return new ErrorResponse(["DuplicateCategory", 'title' => $data['title']], 409);
            } else {
                return new ErrorResponse(["InvalidCategory", 'title' => $data['title']], 422);
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
                    throw new ExceptionInput("whitespace", ['field' => "title", 'action' => __FUNCTION__]);
                }
                $title = Arsse::$user->propertiesSet(Arsse::$user->id, ['root_folder_name' => $title])['root_folder_name'];
            } else {
                Arsse::$db->folderPropertiesSet(Arsse::$user->id, $folder, ['name' => $title]);
            }
        } catch (ExceptionInput $e) {
            if ($e->getCode() === 10236) {
                return new ErrorResponse(["DuplicateCategory", 'title' => $title], 409);
            } elseif (in_array($e->getCode(), [10237, 10239])) {
                return new ErrorResponse("404", 404);
            } else {
                return new ErrorResponse(["InvalidCategory", 'title' => $title], 422);
            }
        }
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        return new Response(['id' => (int) $path[1], 'title' => $title, 'user_id' => $meta['num']], 201);
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
                    Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $sub['id']);
                }
                $tr->commit();
            }
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    protected function transformFeed(array $sub, int $uid, string $rootName, \DateTimeZone $tz): array {
        $url = new Uri($sub['url']);
        return [
            'id'                    => (int) $sub['id'],
            'user_id'               => $uid,
            'feed_url'              => (string) $url->withUserInfo(""),
            'site_url'              => (string) $sub['source'],
            'title'                 => (string) $sub['title'],
            'checked_at'            => Date::normalize($sub['updated'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO),
            'next_check_at'         => $sub['next_fetch'] ? Date::normalize($sub['next_fetch'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO) : "0001-01-01T00:00:00Z",
            'etag_header'           => (string) $sub['etag'],
            'last_modified_header'  => (string) Date::transform($sub['edited'], "http", "sql"),
            'parsing_error_message' => (string) $sub['err_msg'],
            'parsing_error_count'   => (int) $sub['err_count'],
            'scraper_rules'         => "",
            'rewrite_rules'         => "",
            'crawler'               => (bool) $sub['scrape'],
            'blocklist_rules'       => (string) $sub['block_rule'],
            'keeplist_rules'        => (string) $sub['keep_rule'],
            'user_agent'            => "",
            'username'              => rawurldecode(explode(":", $url->getUserInfo(), 2)[0] ?? ""),
            'password'              => rawurldecode(explode(":", $url->getUserInfo(), 2)[1] ?? ""),
            'disabled'              => false,
            'ignore_http_cache'     => false,
            'fetch_via_proxy'       => false,
            'category'              => [
                'id'      => (int) $sub['top_folder'] + 1,
                'title'   => $sub['top_folder_name'] ?? $rootName,
                'user_id' => $uid,
            ],
            'icon'                  => $sub['icon_id'] ? ['feed_id' => (int) $sub['id'], 'icon_id' => (int) $sub['icon_id']] : null,
        ];
    }

    protected function getFeeds(): ResponseInterface {
        $out = [];
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $r) {
            $out[] = $this->transformFeed($r, $meta['num'], $meta['root'], $meta['tz']);
        }
        return new Response($out);
    }

    protected function getCategoryFeeds(array $path): ResponseInterface {
        // transform the category number into a folder number by subtracting one
        $folder = ((int) $path[1]) - 1;
        // unless the folder is root, list recursive
        $recursive = $folder > 0;
        $out = [];
        $tr = Arsse::$db->begin();
        // get the list of subscriptions, or bail
        try {
            $meta = $this->userMeta(Arsse::$user->id);
            foreach (Arsse::$db->subscriptionList(Arsse::$user->id, $folder, $recursive) as $r) {
                $out[] = $this->transformFeed($r, $meta['num'], $meta['root'], $meta['tz']);
            }
        } catch (ExceptionInput $e) {
            // the folder does not exist
            return new ErrorResponse("404", 404);
        }
        return new Response($out);
    }

    protected function getFeed(array $path): ResponseInterface {
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        try {
            $sub = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $path[1]);
            return new Response($this->transformFeed($sub, $meta['num'], $meta['root'], $meta['tz']));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function createFeed(array $data): ResponseInterface {
        try {
            Arsse::$db->feedAdd($data['feed_url'], (string) $data['username'], (string) $data['password'], false, (bool) $data['crawler']);
            $tr = Arsse::$db->begin();
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, $data['feed_url'], (string) $data['username'], (string) $data['password'], false, (bool) $data['crawler']);
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['folder' => $data['category_id'] - 1, 'scrape' => (bool) $data['crawler']]);
            $tr->commit();
            if (strlen($data['keeplist_rules'] ?? "") || strlen($data['blocklist_rules'] ?? "")) {
                // we do rules separately so as not to tie up the database
                Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['keep_rule' => $data['keeplist_rules'], 'block_rule' => $data['blocklist_rules']]);
            }
        } catch (FeedException $e) {
            $msg = [
                10502 => "Fetch404",
                10506 => "Fetch403",
                10507 => "Fetch401",
                10521 => "Fetch404",
                10522 => "FetchFormat",
            ][$e->getCode()] ?? "FetchOther";
            return new ErrorResponse($msg, 502);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10235:
                    return new ErrorResponse("MissingCategory", 422);
                case 10236:
                    return new ErrorResponse("DuplicateFeed", 409);
            }
        }
        return new Response(['feed_id' => $id], 201);
    }

    protected function updateFeed(array $path, array $data): ResponseInterface {
        $in = [];
        foreach (self::FEED_META_MAP as $from => $to) {
            if (isset($data[$from])) {
                $in[$to] = $data[$from];
            }
        }
        if (isset($in['folder'])) {
            $in['folder'] -= 1;
        }
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $path[1], $in);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10231:
                case 10232:
                    return new ErrorResponse("InvalidTitle", 422);
                case 10235:
                    return new ErrorResponse("MissingCategory", 422);
                case 10239:
                    return new ErrorResponse("404", 404);
            }
        }
        return $this->getFeed($path)->withStatus(201);
    }

    protected function deleteFeed(array $path): ResponseInterface {
        try {
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $path[1]);
            return new EmptyResponse(204);
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getFeedIcon(array $path): ResponseInterface {
        try {
            $icon = Arsse::$db->subscriptionIcon(Arsse::$user->id, (int) $path[1]);
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        if (!$icon || !$icon['type'] || !$icon['data']) {
            return new ErrorResponse("404", 404);
        }
        return new Response([
            'id'        => (int) $icon['id'],
            'data'      => $icon['type'].";base64,".base64_encode($icon['data']),
            'mime_type' => $icon['type'],
        ]);
    }

    protected function computeContext(array $query, Context $c): RootContext {
        if ($query['before'] && $query['before']->getTimestamp() === 0) {
            $query['before'] = null; // NOTE: This workaround is needed for compatibility with "Microflux for Miniflux", an Android Client
        }
        $c->limit($query['limit'] ?? self::DEFAULT_ENTRY_LIMIT) // NOTE: This does not honour user preferences
            ->offset($query['offset'])
            ->starred($query['starred'])
            ->modifiedRange($query['after'], $query['before']) // FIXME: This may not be the correct date field
            ->articleRange($query['after_entry_id'] ? $query['after_entry_id'] + 1 : null, $query['before_entry_id'] ? $query['before_entry_id'] - 1 : null) // FIXME: This might be edition
            ->searchTerms(strlen($query['search'] ?? "") ? preg_split("/\s+/", $query['search']) : null); // NOTE: Miniflux matches only whole words; we match simple substrings
        if ($query['category_id']) {
            if ($query['category_id'] === 1) {
                $c->folderShallow(0);
            } else {
                $c->folder($query['category_id'] - 1);
            }
        }
        $status = array_unique($query['status']);
        sort($status);
        if ($status === ["read", "removed"]) {
            $c1 = $c;
            $c2 = clone $c;
            $c = new UnionContext($c1->unread(false), $c2->hidden(true));
        } elseif ($status === ["read", "unread"]) {
            $c->hidden(false);
        } elseif ($status === ["read"]) {
            $c->hidden(false)->unread(false);
        } elseif ($status === ["removed", "unread"]) {
            $c1 = $c;
            $c2 = clone $c;
            $c = new UnionContext($c1->unread(true), $c2->hidden(true));
        } elseif ($status === ["removed"]) {
            $c->hidden(true);
        } elseif ($status === ["unread"]) {
            $c->hidden(false)->unread(true);
        }
        return $c;
    }

    protected function computeOrder(array $query): array {
        $desc = $query['direction'] === "desc" ? " desc" : "";
        if ($query['order'] === "id") {
            return ["id".$desc];
        } elseif ($query['order'] === "status") {
            if (!$desc) {
                return ["hidden", "unread desc"];
            } else {
                return ["hidden desc", "unread"];
            }
        } elseif ($query['order'] === "published_at") {
            return ["modified_date".$desc];
        } elseif ($query['order'] === "category_title") {
            return ["top_folder_name".$desc];
        } elseif ($query['order'] === "category_id") {
            return ["top_folder".$desc];
        } else {
            return [self::DEFAULT_ORDER_COL.$desc];
        }
    }

    protected function transformEntry(array $entry, int $uid, \DateTimeZone $tz): array {
        if ($entry['hidden']) {
            $status = "removed";
        } elseif ($entry['unread']) {
            $status = "unread";
        } else {
            $status = "read";
        }
        if ($entry['media_url']) {
            $enclosures = [
                [
                    'id'        => (int) $entry['id'], // NOTE: We don't have IDs for enclosures, but we also only have one enclosure per entry, so we can just re-use the same ID
                    'user_id'   => $uid,
                    'entry_id'  => (int) $entry['id'],
                    'url'       => $entry['media_url'],
                    'mime_type' => $entry['media_type'] ?: "application/octet-stream",
                    'size'      => 0,
                ],
            ];
        } else {
            $enclosures = null;
        }
        return [
            'id'           => (int) $entry['id'],
            'user_id'      => $uid,
            'feed_id'      => (int) $entry['subscription'],
            'status'       => $status,
            'hash'         => $entry['fingerprint'],
            'title'        => $entry['title'],
            'url'          => $entry['url'],
            'comments_url' => "",
            'published_at' => Date::normalize($entry['published_date'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_SEC),
            'created_at'   => Date::normalize($entry['modified_date'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO),
            'content'      => $entry['content'],
            'author'       => (string) $entry['author'],
            'share_code'   => "",
            'starred'      => (bool) $entry['starred'],
            'reading_time' => 0,
            'enclosures'   => $enclosures,
            'feed'         => null,
        ];
    }

    protected function listEntries(array $query, Context $c): array {
        $c = $this->computeContext($query, $c);
        $order = $this->computeOrder($query);
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        // compile the list of entries
        $out = [];
        foreach (Arsse::$db->articleList(Arsse::$user->id, $c, self::ARTICLE_COLUMNS, $order) as $entry) {
            $out[] = $this->transformEntry($entry, $meta['num'], $meta['tz']);
        }
        // next compile a map of feeds to add to the entries
        if ($out) {
            $feeds = [];
            foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $r) {
                $feeds[(int) $r['id']] = $this->transformFeed($r, $meta['num'], $meta['root'], $meta['tz']);
            }
            // add the feed objects to each entry
            // NOTE: If ever we implement multiple enclosure, this would be the right place to add them
            for ($a = 0; $a < sizeof($out); $a++) {
                $out[$a]['feed'] = $feeds[$out[$a]['feed_id']];
            }
        }
        // finally compute the total number of entries match the query, where necessary
        $count = sizeof($out);
        if ($c->offset || ($c->limit && $count >= $c->limit)) {
            $count = Arsse::$db->articleCount(Arsse::$user->id, (clone $c)->limit(0)->offset(0));
        }
        return ['total' => $count, 'entries' => $out];
    }

    protected function findEntry(int $id, Context $c = null): array {
        $c = ($c ?? new Context)->article($id);
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        // find the entry we want
        $entry = Arsse::$db->articleList(Arsse::$user->id, $c, self::ARTICLE_COLUMNS)->getRow();
        if (!$entry) {
            throw new ExceptionInput("idMissing", ['id' => $id, 'field' => 'entry']);
        }
        $out = $this->transformEntry($entry, $meta['num'], $meta['tz']);
        // next transform the parent feed of the entry
        $out['feed'] = $this->transformFeed(Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $out['feed_id']), $meta['num'], $meta['root'], $meta['tz']);
        return $out;
    }

    protected function getEntries(array $query): ResponseInterface {
        try {
            return new Response($this->listEntries($query, new Context));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("MissingCategory", 400);
        }
    }

    protected function getFeedEntries(array $path, array $query): ResponseInterface {
        $c = (new Context)->subscription((int) $path[1]);
        try {
            return new Response($this->listEntries($query, $c));
        } catch (ExceptionInput $e) {
            // FIXME: this should differentiate between a missing feed and a missing category, but doesn't
            return new ErrorResponse("404", 404);
        }
    }

    protected function getCategoryEntries(array $path, array $query): ResponseInterface {
        $query['category_id'] = (int) $path[1];
        try {
            return new Response($this->listEntries($query, new Context));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getEntry(array $path): ResponseInterface {
        try {
            return new Response($this->findEntry((int) $path[1]));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getFeedEntry(array $path): ResponseInterface {
        $c = (new Context)->subscription((int) $path[1]);
        try {
            return new Response($this->findEntry((int) $path[3], $c));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function getCategoryEntry(array $path): ResponseInterface {
        $c = new Context;
        if ($path[1] === "1") {
            $c->folderShallow(0);
        } else {
            $c->folder((int) $path[1] - 1);
        }
        try {
            return new Response($this->findEntry((int) $path[3], $c));
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
    }

    protected function updateEntries(array $data): ResponseInterface {
        if ($data['status'] === "read") {
            $in = ['read' => true, 'hidden' => false];
        } elseif ($data['status'] === "unread") {
            $in = ['read' => false, 'hidden' => false];
        } elseif ($data['status'] === "removed") {
            $in = ['read' => true, 'hidden' => true];
        }
        assert(isset($in), new \Exception("Unknown status specified"));
        Arsse::$db->articleMark(Arsse::$user->id, $in, (new Context)->articles($data['entry_ids']));
        return new EmptyResponse(204);
    }

    protected function massRead(Context $c): void {
        Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c->hidden(false));
    }

    protected function markUserByNum(array $path): ResponseInterface {
        // this function is restricted to the logged-in user
        $user = Arsse::$user->propertiesGet(Arsse::$user->id, false);
        if (((int) $path[1]) !== $user['num']) {
            return new ErrorResponse("403", 403);
        }
        $this->massRead(new Context);
        return new EmptyResponse(204);
    }

    protected function markFeed(array $path): ResponseInterface {
        try {
            $this->massRead((new Context)->subscription((int) $path[1]));
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
            $c->folderShallow($folder);
        } else {
            $c->folder($folder);
        }
        try {
            $this->massRead($c);
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    protected function toggleEntryBookmark(array $path): ResponseInterface {
        // NOTE: A toggle is bad design, but we have no choice but to implement what Miniflux does
        $id = (int) $path[1];
        $c = (new Context)->article($id);
        try {
            $tr = Arsse::$db->begin();
            if (Arsse::$db->articleCount(Arsse::$user->id, (clone $c)->starred(false))) {
                Arsse::$db->articleMark(Arsse::$user->id, ['starred' => true], $c);
            } else {
                Arsse::$db->articleMark(Arsse::$user->id, ['starred' => false], $c);
            }
            $tr->commit();
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    protected function refreshFeed(array $path): ResponseInterface {
        // NOTE: This is a no-op; we simply check that the feed exists
        try {
            Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $path[1]);
        } catch (ExceptionInput $e) {
            return new ErrorResponse("404", 404);
        }
        return new EmptyResponse(204);
    }

    protected function refreshAllFeeds(): ResponseInterface {
        // NOTE: This is a no-op
        // It could be implemented, but the need is considered low since we use a dynamic schedule always
        return new EmptyResponse(204);
    }

    protected function opmlImport(string $data): ResponseInterface {
        try {
            Arsse::$obj->get(OPML::class)->import(Arsse::$user->id, $data);
        } catch (ImportException $e) {
            switch ($e->getCode()) {
                case 10611:
                    return new ErrorResponse("InvalidBodyXML", 400);
                case 10612:
                    return new ErrorResponse("InvalidBodyOPML", 422);
                case 10613:
                    return new ErrorResponse("InvalidImportCategory", 422);
                case 10614:
                    return new ErrorResponse("DuplicateImportCategory", 422);
                case 10615:
                    return new ErrorResponse("InvalidImportLabel", 422);
            }
        } catch (FeedException $e) {
            return new ErrorResponse(["FailedImportFeed", 'url' => $e->getParams()['url'], 'code' => $e->getCode()], 502);
        }
        return new Response(['message' => Arsse::$lang->msg("API.Miniflux.ImportSuccess")]);
    }

    protected function opmlExport(): ResponseInterface {
        return new GenericResponse(Arsse::$obj->get(OPML::class)->export(Arsse::$user->id), 200, ['Content-Type' => "application/xml"]);
    }
}
