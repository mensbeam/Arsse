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
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\REST\Exception;
use JKingWeb\Arsse\Rule\Rule;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\Exception as UserException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Uri;

class V1 extends \JKingWeb\Arsse\REST\AbstractHandler {
    // NOTE: Commit, build date,  and Go version are synthetic
    //   data taken from the Arch package for the 2.2.7 release of Miniflux
    public const VERSION = "2.2.7";
    public const COMMIT = "f99dff5238484c5f22b204c464239bec716976f5";
    public const BUILD_DATE = "2025-04-01-17:18:32";
    public const GO_VERSION = "go1.24.1";

    protected const ACCEPTED_TYPES_OPML = ["application/xml", "text/xml", "text/x-opml"];
    protected const ACCEPTED_TYPES_JSON = ["application/json"];
    protected const DEFAULT_ENTRY_LIMIT = 100;
    protected const DEFAULT_ORDER_COL = "modified_date";
    protected const DATE_FORMAT_SEC = "Y-m-d\TH:i:sP";
    protected const DATE_FORMAT_MICRO = "Y-m-d\TH:i:s.uP";
    /** The list of valid URL query keys and the types they are converted to */
    protected const VALID_QUERY = [
        // All dates are in the form of UNIX timestamps
        'status'           => V::T_STRING + V::M_ARRAY,
        'offset'           => V::T_INT,
        'limit'            => V::T_INT,
        'order'            => V::T_STRING,
        'direction'        => V::T_STRING,
        'before'           => V::T_DATE,
        'after'            => V::T_DATE,
        'published_before' => V::T_DATE,
        'published_after'  => V::T_DATE,
        'changed_before'   => V::T_DATE,
        'changed_after'    => V::T_DATE,
        'before_entry_id'  => V::T_INT,
        'after_entry_id'   => V::T_INT,
        'starred'          => V::T_BOOL,
        'search'           => V::T_STRING,
        'category_id'      => V::T_INT,
        'counts'           => V::T_BOOL,
    ];
    /** The list of valid JSON body keys and the types of their values
     * 
     * If a type in the input does not match, the entire request is rejected,
     * so we compare against PHP type names instead of using our value
     * conversion infrastructure.
     * 
     * Not all these properties are used by our implementation, but they are
     * treated with the same strictness as in Miniflux on the assumption this
     * will ease interoperability.
     */
    protected const VALID_JSON = [
        // 
        'url'                                  => "string",
        'username'                             => "string",
        'password'                             => "string",
        'user_agent'                           => "string",
        'title'                                => "string",
        'feed_url'                             => "string",
        'category_id'                          => "integer",
        'crawler'                              => "boolean",
        'user_agent'                           => "string",
        'scraper_rules'                        => "string",
        'rewrite_rules'                        => "string",
        'keeplist_rules'                       => "string",
        'blocklist_rules'                      => "string",
        'disabled'                             => "boolean",
        'hide_globally'                        => "boolean",
        'ignore_http_cache'                    => "boolean",
        'fetch_via_proxy'                      => "boolean",
        'entry_ids'                            => "array", // this is a special case: it is an array of integers
        'status'                               => "string",
        'cookie'                               => "string",
        'description'                          => "string",
        'site_url'                             => "string",
        'disable_http2'                        => "boolean",
        'allow_self_signed_certificates'       => "boolean",
        'is_admin'                             => "boolean",
        'theme'                                => "string",
        'language'                             => "string",
        'timezone'                             => "string",
        'entry_sorting_direction'              => "string",
        'entry_sorting_order'                  => "string",
        'stylesheet'                           => "string",
        'custom_js'                            => "string",
        'external_font_hosts'                  => "string",
        'entries_per_page'                     => "integer",
        'keyboard_shortcuts'                   => "boolean",
        'show_reading_time'                    => "boolean",
        'entry_swipe'                          => "boolean",
        'gesture_nav'                          => "string",
        'display_mode'                         => "string",
        'default_reading_speed'                => "integer",
        'cjk_reading_speed'                    => "integer",
        'default_home_page'                    => "string",
        'categories_sorting_order'             => "string",
        'mark_read_on_view'                    => "boolean",
        'mark_read_on_media_player_completion' => "boolean",
        'media_playback_rate'                  => "integer",
        'block_filter_entry_rules'             => "string",
        'keep_filter_entry_rules'              => "string",
    ];
    /** The list of inputs which are enumerations, and their valid values
     * 
     * This list includes both URL query keys and JSON body keys.
     */
    protected const VALID_ENUM = [
        'order'                    => ["id", "status", "published_at", "category_title", "category_id", "title", "author"],
        'direction'                => ["asc", "desc"],
        'status'                   => ["read", "unread", "removed"],
        'theme'                    => ["dark_sans_serif", "dark_serif", "light_sans_serif", "light_serif", "system_sans_serif", "system_serif"],
        'entry_sorting_direction'  => ["asc", "desc"],
        'entry_sorting_order'      => ["published_at", "created_at"],
        'gesture_nav'              => ["none", "tap", "swipe"],
        'display_mode'             => ["fullscreen", "standalone", "minimal-ui", "browser"],
        'default_home_page'        => ["categories", "feeds", "history", "starred", "unread"],
        'categories_sorting_order' => ["unread_count", "alphabetical"],
    ];
    /** The list of inputs which must be integers greater than zero */
    protected const VALID_ONE_OR_MORE = ["category_id", "before_entry_id", "after_entry_id", "entries_per_page", "default_reading_speed", "cjk_reading_speed", "media_playback_rate"];
    /** A map between Miniflux's input properties and our input properties when modifiying feeds
     *
     * Miniflux also allows changing the following properties:
     *
     * - description
     * - site_url
     * - scraper_rules
     * - rewrite_rules
     * - disabled
     * - hide_globally
     * - no_media_player
     * - ignore_http_cache
     * - fetch_via_proxy
     * - disable_http2
     * - allow_self_signed_certificates
     *
     * We do not implement these for various reasons, such as due to lack of
     * underlying functionality in our implementation, or difficulty of
     * implementation for minimal reward.
     * The properties are still checked for type and syntactic validity
     * where practical, on the assumption Miniflux would also reject
     * invalid values.
     */
    protected const FEED_META_MAP = [
        'feed_url'        => "url",
        'username'        => "username",
        'password'        => "password",
        'title'           => "title",
        'category_id'     => "folder",
        'crawler'         => "scrape",
        'keeplist_rules'  => "keep_rule",
        'blocklist_rules' => "block_rule",
        'user_agent'      => "user_agent",
        'cookie'          => "cookie",
    ];
    /** A map between Miniflux's input properties and our input properties when modifiying feeds */
    protected const CATEGORY_META_MAP = [
        'title' => "name",
    ];
    /** A map between Miniflux user preferences/metadata and our generic
     * metadata properties. These are properties which do (or can) apply to
     * protocols besides Miniflux. Miniflux-specific preferences which have no
     * effect in The Arsse itself are listed below.
     */
    protected const USER_META_MAP = [
        'id'                                   => "num", // read-only
        'is_admin'                             => "admin", // write-restricted
        'language'                             => "lang",
        'timezone'                             => "tz",
    ];
    /** A list of Miniflux-sp */
    protected const USER_META_DEFAULTS = [
        'theme'                                => "light_serif",
        'entry_sorting_direction'              => "asc",
        'entry_sorting_order'                  => "published_at",
        'stylesheet'                           => "",
        'custom_js'                            => "",
        'external_font_hosts'                  => "",
        'entries_per_page'                     => 100,
        'keyboard_shortcuts'                   => true,
        'show_reading_time'                    => true,
        'entry_swipe'                          => true,
        'gesture_nav'                          => "tap",
        'display_mode'                         => "standalone",
        'default_reading_speed'                => 265,
        'cjk_reading_speed'                    => 500,
        'default_home_page'                    => "unread",
        'categories_sorting_order'             => "unread_count",
        'mark_read_on_view'                    => true,
        'mark_read_on_media_player_completion' => false,
        'media_playback_rate'                  => 1,
        'block_filter_entry_rules'             => "",
        'keep_filter_entry_rules'              => "",
    ];
    protected const ARTICLE_COLUMNS = [
        "id", "url", "title", "subscription",
        "author", "fingerprint", "published_date",
        "added_date", "modified_date",
        "starred", "unread", "hidden",
        "content", "media_url", "media_type"
    ];
    protected const CALLS = [                // handler method        Admin  Path   Body   Query  Required fields
        '/categories'                    => [
            'GET'                        => ["getCategories",         false, false, false, true, []],
            'POST'                       => ["createCategory",        false, false, true,  false, ["title"]],
        ],
        '/categories/1'                  => [
            'PUT'                        => ["updateCategory",        false, true,  true,  false, []],
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
        '/enclosures/1'                  => [
            'GET'                        => ["getEnclosure",          false, true,  false, false, []],
        ],
        '/entries'                       => [
            'GET'                        => ["getEntries",            false, false, false, true,  []],
            'PUT'                        => ["updateEntries",         false, false, true,  false, ["entry_ids", "status"]],
        ],
        '/entries/1'                     => [
            'GET'                        => ["getEntry",              false, true,  false, false, []],
            'PUT'                        => ["updateEntry",           false, true,  true,  false, []],
        ],
        '/entries/1/fetch-content'       => [
            'GET'                        => ["scrapeEntry",           false, true,  false, false, []],
        ],
        '/entries/1/save'                => [
            'POST'                       => ["saveEntry",             false, false, false, false, []],
        ],
        '/entries/1/bookmark'            => [
            'PUT'                        => ["toggleEntryBookmark",   false, true,  false, false, []],
        ],
        '/export'                        => [
            'GET'                        => ["opmlExport",            false, false, false, false, []],
        ],
        '/feeds'                         => [
            'GET'                        => ["getFeeds",              false, false, false, false, []],
            'POST'                       => ["createFeed",            false, false, true,  false, ["feed_url"]],
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
        '/feeds/counters'                => [
            'GET'                        => ["getFeedCounters",       false, false, false, false, []],
        ],
        '/feeds/refresh'                 => [
            'PUT'                        => ["refreshAllFeeds",       false, false, false, false, []],
        ],
        '/flush-history'                 => [
            'PUT'                        => ["flushHistory",          false, false, false, false, []],
            'DELETE'                     => ["flushHistory",          false, false, false, false, []],
        ],
        '/icons/1'                       => [
            'GET'                        => ["getIcon",               false, true,  false, false, []],
        ],
        '/import'                        => [
            'POST'                       => ["opmlImport",            false, false, true,  false, []],
        ],
        '/integrations/status'           => [
            'GET'                        => ["getIntegrations",       false, false, false, false, []],
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
        '/version'                       => [
            'GET'                        => ["getVersion",            false, false,  false, false, []],
        ],
    ];

    public function __construct() {
    }

    public static function respError($data, int $status = 400, array $headers = []): ResponseInterface {
        assert(isset(Arsse::$lang) && Arsse::$lang instanceof \JKingWeb\Arsse\Lang, new \Exception("Language database must be initialized before use"));
        $data = (array) $data;
        $msg = array_shift($data);
        $data = ["error_message" => Arsse::$lang->msg("API.Miniflux.Error.".$msg, $data)];
        return HTTP::respJson($data, $status, $headers);
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
            return self::respError("401", 401);
        }
        $func = $this->chooseCall($target, $method);
        if ($func instanceof ResponseInterface) {
            return $func;
        } else {
            [$func, $reqAdmin, $reqPath, $reqBody, $reqQuery, $reqFields] = $func;
        }
        if ($reqAdmin && !$this->isAdmin()) {
            return self::respError("403", 403);
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
                        return self::respError(["InvalidBodyJSON", json_last_error_msg()], 400);
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
            return HTTP::respEmpty(400);
        } catch (AbstractException $e) {
            // if there was any other Arsse exception return 500
            return HTTP::respEmpty(500);
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
                return HTTP::respEmpty(405, ['Allow' => implode(", ", array_keys(self::CALLS[$url]))]);
            }
        } else {
            // if the path is not supported, return 404
            return HTTP::respEmpty(404);
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
                // if a valid key is missing set it to null so that any key may be accessed safely
                $body[$k] = null;
            } elseif (gettype($body[$k]) !== $t) {
                return self::respError(["InvalidInputType", 'field' => $k, 'expected' => $t, 'actual' => gettype($body[$k])], 422);
            } elseif (
                (isset(self::VALID_ENUM[$k]) && !in_array($body[$k], self::VALID_ENUM[$k]))
                || (in_array($k, ["keeplist_rules", "blocklist_rules"]) && !Rule::validate($body[$k]))
                || (in_array($k, ["url", "feed_url", "site_url"]) && !URL::absolute($body[$k]))
                || (in_array($k, self::VALID_ONE_OR_MORE) && $body[$k] < 1)
            ) {
                return self::respError(["InvalidInputValue", 'field' => $k], 422);
            } elseif ($k === "entry_ids") {
                foreach ($body[$k] as $v) {
                    if (gettype($v) !== "integer") {
                        return self::respError(["InvalidInputType", 'field' => $k, 'expected' => "integer", 'actual' => gettype($v)], 422);
                    } elseif ($v < 1) {
                        return self::respError(["InvalidInputValue", 'field' => $k], 422);
                    }
                }
            }
        }
        // check for any missing required values
        foreach ($req as $k) {
            if (!isset($body[$k]) || (is_array($body[$k]) && !$body[$k])) {
                return self::respError(["MissingInputValue", 'field' => $k], 422);
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
                    return self::respError(["DuplicateInputValue", 'field' => $k], 400);
                }
                $seen[$k] = true;
                if ($v === "" || ($t === V::T_DATE && $v === "0")) {
                    // if the value is empty we can discard the value, but subsequent values for the same non-array key are still considered duplicates
                    // for date fields a value of zero is also considered empty
                    continue;
                } elseif ($a) {
                    $out[$k][] = V::normalize($v, $t + V::M_STRICT, "unix");
                } else {
                    $out[$k] = V::normalize($v, $t + V::M_STRICT, "unix");
                }
            } catch (ExceptionType $e) {
                return self::respError(["InvalidInputValue", 'field' => $k], 400);
            }
            // perform additional validation
            if (
                (isset(self::VALID_ENUM[$k]) && !in_array($v, self::VALID_ENUM[$k]))
                || (in_array($k, self::VALID_ONE_OR_MORE) && $v < 1)
                || (in_array($k, ["limit", "offset"]) && $v < 0)
            ) {
                return self::respError(["InvalidInputValue", 'field' => $k], 400);
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
            return HTTP::challenge(HTTP::respEmpty(204, [
                'Allow'  => implode(", ", $allowed),
                'Accept' => implode(", ", $url === "/import" ? self::ACCEPTED_TYPES_OPML : self::ACCEPTED_TYPES_JSON),
            ]));
        } else {
            // if the path is not supported, return 404
            return HTTP::respEmpty(404);
        }
    }

    protected function transformUser(string $user, array $data, ?\DateTimeImmutable $now = null): array {
        $now = $now ?? $this->now();
        $keys = [
            "id",
            "username",
            "is_admin",
            "theme",
            "language",
            "timezone",
            "entry_sorting_direction",
            "entry_sorting_order",
            "stylesheet",
            "custom_js",
            "external_font_hosts",
            "google_id",
            "openid_connect_id",
            "entries_per_page",
            "keyboard_shortcuts",
            "show_reading_time",
            "entry_swipe",
            "gesture_nav",
            "last_login_at",
            "display_mode",
            "default_reading_speed",
            "cjk_reading_speed",
            "default_home_page",
            "categories_sorting_order",
            "mark_read_on_view",
            "mark_read_on_media_player_completion",
            "media_playback_rate",
            "block_filter_entry_rules",
            "keep_filter_entry_rules",
        ];
        $out = [];
        foreach ($keys as $k) {
            switch ($k) {
                case "username":
                    $out[$k] = $user;
                    break;
                case "language":
                    $out[$k] = $data[$k] ?? "en_US";
                    break;
                case "timezone":
                    $out[$k] = $data[$k] ?? "UTC";
                    break;
                case "google_id":
                case "openid_connect_id":
                    $out[$k] = "";
                    break;
                case "last_login_at":
                    $out[$k] = Date::transform($now, "iso8601m");
                    break;
                default:
                    $out[$k] = $data[$k] ?? self::USER_META_DEFAULTS[$k];
            }
        }
        return $out;
    }
    
    protected function transformCategory(array $folder, int $uid): array {
        $out = [
            // always add 1 to the ID since the root folder will always be 1 instead of 0.
            'id'            => ((int) $folder['id']) + 1,
            'title'         => $folder['name'],
            'user_id'       => $uid,
            'hide_globally' => false,
        ];
        if (isset($folder['unread'])) {
            $out['feed_count'] = $folder['feeds'];
            $out['total_unread'] = $folder['unread'];
        }
        return $out;
    }

    protected function transformFeed(array $sub, int $uid, string $rootName, \DateTimeZone $tz): array {
        $url = new Uri($sub['url']);
        return [
            'id'                             => (int) $sub['id'],
            'user_id'                        => $uid,
            'feed_url'                       => (string) $url->withUserInfo(""),
            'site_url'                       => (string) $sub['source'],
            'title'                          => (string) $sub['title'],
            'description'                    => "",
            'checked_at'                     => Date::normalize($sub['updated'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO),
            'next_check_at'                  => $sub['next_fetch'] ? Date::normalize($sub['next_fetch'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO) : "0001-01-01T00:00:00Z",
            'etag_header'                    => (string) $sub['etag'],
            'last_modified_header'           => (string) Date::transform($sub['edited'], "http", "sql"),
            'parsing_error_message'          => (string) $sub['err_msg'],
            'parsing_error_count'            => (int) $sub['err_count'],
            'scraper_rules'                  => "",
            'rewrite_rules'                  => "",
            'crawler'                        => (bool) $sub['scrape'],
            'blocklist_rules'                => (string) $sub['block_rule'],
            'keeplist_rules'                 => (string) $sub['keep_rule'],
            'urlrewrite_rules'               => "",
            'user_agent'                     => (string) $sub['user_agent'],
            'cookie'                         => (string) $sub['cookie'],
            'username'                       => rawurldecode(explode(":", $url->getUserInfo(), 2)[0] ?? ""),
            'password'                       => rawurldecode(explode(":", $url->getUserInfo(), 2)[1] ?? ""),
            'disabled'                       => false,
            'no_media_player'                => false,
            'ignore_http_cache'              => false,
            'allow_self_signed_certificates' => false,
            'fetch_via_proxy'                => false,
            'hide_globally'                  => false,
            'disable_http2'                  => false,
            'apprise_service_urls'           => "",
            'webhook_url'                    => "",
            'ntfy_enabled'                   => false,
            'ntfy_priority'                  => 3,
            'ntfy_topic'                     => "",
            'category'                       => $this->transformCategory(['id' => $sub['top_folder'], 'name' => $sub['top_folder_name'] ?? $rootName], $uid),
            'icon'                           => [
                'feed_id'          => (int) $sub['id'],
                'icon_id'          => (int) $sub['icon_id'],
                'external_icon_id' => (string) $sub['icon_id'],
            ],
        ];
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
            $enclosures = [$this->transformEnclosure($entry, $uid)];
        } else {
            $enclosures = [];
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
            'created_at'   => Date::normalize($entry['added_date'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO),
            'changed_at'   => Date::normalize($entry['modified_date'], "sql")->setTimezone($tz)->format(self::DATE_FORMAT_MICRO),
            'content'      => $entry['content'],
            'author'       => (string) $entry['author'],
            'share_code'   => "",
            'starred'      => (bool) $entry['starred'],
            'reading_time' => 0,
            'enclosures'   => $enclosures,
            'feed'         => null, // filled in elsewhere
            'tags'         => null, // filled in elsewhere
        ];
    }

    protected function transformEnclosure(array $entry, int $uid): array {
        return [
            'id'                => (int) $entry['id'], // NOTE: We don't have IDs for enclosures, but we also only have one enclosure per entry, so we can just re-use the same ID
            'user_id'           => $uid,
            'entry_id'          => (int) $entry['id'],
            'url'               => $entry['media_url'],
            'mime_type'         => $entry['media_type'] ?: "application/octet-stream",
            'size'              => 0,
            'media_progression' => 0,
        ];
    }

    protected function transformIcon(array $icon): array {
        $type = $icon['type'] ?: "application/octet-stream";
        return [
            'id'        => (int) $icon['id'],
            'mime_type' => $type,
            'data'      => $type.";base64,".base64_encode($icon['data']),
        ];
    }

    protected function discoverSubscriptions(array $data): ResponseInterface {
        try {
            $url = URL::normalize((string) $data['url'], $data['username'], $data['password']);
            $list = Feed::discoverAll($url, $data['user_agent'], $data['cookie']);
        } catch (FeedException $e) {
            $msg = [
                10502 => "Fetch404",
                10506 => "Fetch403",
                10507 => "Fetch401",
                10521 => "Fetch404",
            ][$e->getCode()] ?? "FetchOther";
            return self::respError($msg, 502);
        }
        $out = [];
        foreach ($list as $url) {
            // TODO: This needs to be refined once PicoFeed is replaced
            $out[] = ['title' => "Feed", 'type' => "rss", 'url' => $url];
        }
        return HTTP::respJson($out);
    }

    protected function getPrefs(string $user): array {
        $tr = Arsse::$user->begin();
        $meta = Arsse::$user->propertiesGet($user);
        $prefs = Arsse::$db->userPropertiesGet($user);
        if (isset($prefs['miniflux_prefs'])) {
            // if there is a key for Miniflux preferences, use it
            $out = @json_decode($prefs['miniflux_prefs'], true) ?: [];
        } else {
            // otherwise map old metadata which may have been set in versions of The Arsse prior to 0.12.0
            $oldMap = [
                'theme'                   => ["theme",        V::T_STRING],
                'entry_sorting_direction' => ["sort_asc",     V::T_BOOL],
                'entries_per_page'        => ["page_size",    V::T_INT],
                'keyboard_shortcuts'      => ["shortcuts",    V::T_BOOL],
                'show_reading_time'       => ["reading_time", V::T_BOOL],
                'entry_swipe'             => ["gestures",     V::T_BOOL],
                'stylesheet'              => ["stylesheet",   V::T_STRING],
            ];
            $out = [];
            foreach ($oldMap as $to => [$from, $type]) {
                if (isset($prefs[$from])) {
                    $out[$to] = V::normalize($prefs[$from] , $type);
                }
            }
            if (isset($out['entry_sorting_direction'])) {
                $out['entry_sorting_direction'] = ($out['entry_sorting_direction'] ?? false) ? "asc" : "desc";
            }
        }
        // add general metadata under Miniflux keys
        foreach (self::USER_META_MAP as $to => $from) {
            if (isset($meta[$from])) {
                $out[$to] = $meta[$from];
            }
        }
        return $out;
    }

    protected function editUserPrefs(string $user, array $data): array {
        $data = array_filter($data, function($v) {
            // we filter out nulls because every possible input key is
            //   populated by our input normalizer, so merging would simply
            //   overwrite all the defaults (leaving a bunch of nulls), and
            //   comparing keys would yield false positives
            return isset($v);
        });
        $tr = Arsse::$user->begin();
        // start by getting the current user metadata and merging in the new data
        unset($data['id']); // read-only; Miniflux ignores this altogether if you supply it
        $newState = array_merge($this->getPrefs($user), $data);
        // map Miniflux properties to internal metadata properties, and filter out anything else which is set to its default
        $meta = [];
        $prefs = [];
        foreach (self::USER_META_MAP as $from => $to) {
            if (isset($data[$from])) {
                $meta[$to] = $data[$from];
            }
        }
        foreach (self::USER_META_DEFAULTS as $key => $default) {
            if (isset($newState[$key]) && $newState[$key] !== $default) {
                $prefs[$key] = $newState[$key];
            }
        }
        // make any requested changes
        if ($meta) {
            Arsse::$user->propertiesSet($user, $meta);
        }
        if ($prefs && array_intersect_key($prefs, $data)) {
            Arsse::$db->userPropertiesSet($user, ['miniflux_prefs' => json_encode($prefs, \JSON_UNESCAPED_SLASHES)]);
        }
        // read out the newly-modified user and commit the changes
        $out = $this->transformUser($user, $newState);
        $tr->commit();
        // add the input password if a password change was requested
        if (isset($data['password'])) {
            $out['password'] = $data['password'];
        }
        return $out;
    }

    protected function getUsers(): ResponseInterface {
        $now = $this->now();
        $tr = Arsse::$user->begin();
        $out = [];
        foreach (Arsse::$user->list() as $user) {
            try {
                $out[] = $this->transformUser($user, $this->getPrefs($user), $now);
            } catch (UserException $e) {
                continue;
            }
        }
        return HTTP::respJson($out);
    }

    protected function getUserById(array $path): ResponseInterface {
        try {
            $user = $path[1];
            return HTTP::respJson($this->transformUser($user, $this->getPrefs($user)));
        } catch (UserException $e) {
            return self::respError("404", 404);
        }
    }

    protected function getUserByNum(array $path): ResponseInterface {
        try {
            $user = Arsse::$user->lookup((int) $path[1]);
            return HTTP::respJson($this->transformUser($user, $this->getPrefs($user)));
        } catch (UserException $e) {
            return self::respError("404", 404);
        }
    }

    protected function getCurrentUser(): ResponseInterface {
        $user = Arsse::$user->id;
        return HTTP::respJson($this->transformUser($user, $this->getPrefs($user)));
    }

    protected function createUser(array $data): ResponseInterface {
        try {
            $tr = Arsse::$user->begin();
            $data['password'] = Arsse::$user->add($data['username'], $data['password']);
            $out = $this->editUserPrefs($data['username'], $data);
            $tr->commit();
        } catch (UserException $e) {
            switch ($e->getCode()) {
                case 10403:
                    return self::respError(["DuplicateUser", 'user' => $data['username']], 409);
                case 10441:
                    return self::respError(["InvalidInputValue", 'field' => "timezone"], 422);
                case 10444:
                    return self::respError(["InvalidInputValue", 'field' => "username"], 422);
            }
            throw $e; // @codeCoverageIgnore
        }
        return HTTP::respJson($out, 201);
    }

    protected function updateUserByNum(array $path, array $data): ResponseInterface {
        $tr = Arsse::$user->begin();
        // this function is restricted to admins unless the affected user and calling user are the same
        $self = Arsse::$user->propertiesGet(Arsse::$user->id);
        if (((int) $path[1]) === $self['num']) {
            if ($data['is_admin'] && !$self['admin']) {
                // non-admins should not be able to set themselves as admin
                return self::respError("InvalidElevation", 403);
            }
            $user = Arsse::$user->id;
        } elseif (!$self['admin']) {
            return self::respError("403", 403);
        } else {
            try {
                $user = Arsse::$user->lookup((int) $path[1]);
            } catch (ExceptionConflict $e) {
                return self::respError("404", 404);
            }
        }
        // make any requested changes
        try {
            if (isset($data['username'])) {
                Arsse::$user->rename($user, $data['username']);
                $user = $data['username'];
            }
            if (isset($data['password'])) {
                Arsse::$user->passwordSet($user, $data['password']);
            }
            $out = $this->editUserPrefs($user, $data);
            $tr->commit();
        } catch (UserException $e) {
            switch ($e->getCode()) {
                case 10403:
                    return self::respError(["DuplicateUser", 'user' => $data['username']], 409);
                case 10441:
                    return self::respError(["InvalidInputValue", 'field' => "timezone"], 422);
                case 10444:
                    return self::respError(["InvalidInputValue", 'field' => "username"], 422);
            }
            throw $e; // @codeCoverageIgnore
        }
        return HTTP::respJson($out, 201);
    }

    protected function deleteUserByNum(array $path): ResponseInterface {
        try {
            Arsse::$user->remove(Arsse::$user->lookup((int) $path[1]));
        } catch (ExceptionConflict $e) {
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
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
        $meta = Arsse::$user->propertiesGet($user);
        return [
            'num'  => $meta['num'],
            'root' => $meta['root_folder_name'] ?? Arsse::$lang->msg("API.Miniflux.DefaultCategoryName"),
            'tz'   => new \DateTimeZone($meta['tz'] ?? "UTC"),
        ];
    }

    protected function getCategories(array $query): ResponseInterface {
        // if counts are requested, compute unread count for each topmost
        //   folder, and figure out how many feeds are in the root folder
        $unread = [];
        $inRoot = 0;
        $tr = Arsse::$db->begin();
        if ($query['counts']) {
            foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $f) {
                $top = $f['top_folder'] ?? 0;
                if (!isset($unread[$top])) {
                    $unread[$top] = 0;
                }
                $unread[$top] += $f['unread'];
                if ($top === 0) {
                    $inRoot++;
                }
            }
        }
        $out = [];
        // add the root folder as a category
        $meta = $this->userMeta(Arsse::$user->id);
        if ($query['counts']) {
            $out[] = $this->transformCategory(['id' => null, 'name' => $meta['root'], 'feeds' => $inRoot, 'unread' => $unread[0] ?? 0], $meta['num']);
        } else {
            $out[] = $this->transformCategory(['id' => null, 'name' => $meta['root']], $meta['num']);
        }
        // add other top folders as categories
        foreach (Arsse::$db->folderList(Arsse::$user->id, null, false) as $f) {
            if ($query['counts']) {
                $f['unread'] = $unread[$f['id']] ?? 0;
            }
            $out[] = $this->transformCategory($f, $meta['num']);
        }
        return HTTP::respJson($out);
    }

    protected function createCategory(array $data): ResponseInterface {
        $in = [];
        foreach (self::CATEGORY_META_MAP as $from => $to) {
            if (isset($data[$from])) {
                $in[$to] = $data[$from];
            }
        }
        try {
            $id = Arsse::$db->folderAdd(Arsse::$user->id, $in);
        } catch (ExceptionInput $e) {
            if ($e->getCode() === 10236) {
                return self::respError(["DuplicateCategory", 'title' => $data['title']], 409);
            } else {
                return self::respError(["InvalidCategory", 'title' => $data['title']], 422);
            }
        }
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id);
        $in['id'] = $id;
        return HTTP::respJson($this->transformCategory($in, $meta['num']), 201);
    }

    protected function updateCategory(array $path, array $data): ResponseInterface {
        // category IDs in Miniflux are always greater than 1; we have folder 0, so we decrement category IDs by 1 to get the folder ID
        $folder = $path[1] - 1;
        $in = [];
        foreach (self::CATEGORY_META_MAP as $from => $to) {
            if (isset($data[$from])) {
                $in[$to] = $data[$from];
            }
        }
        try {
            if ($folder === 0 && isset($in['name'])) {
                // NOTE: Folder 0 doesn't actually exist in the database, so
                //   its name is kept as user metadata and we have to handle it
                //   separately. One of the implications of this is that the
                //   root folder may share a name with a top-level concrete
                //   folder and not cause a conflict
                if (!strlen(trim($in['name']))) {
                    throw new ExceptionInput("whitespace", ['field' => "title", 'action' => __FUNCTION__]);
                }
                Arsse::$user->propertiesSet(Arsse::$user->id, ['root_folder_name' => $in['name']]);
                unset($in['name']);
            }
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, $folder, $in);
        } catch (ExceptionInput $e) {
            if ($e->getCode() === 10236) {
                return self::respError(["DuplicateCategory", 'title' => $in['name']], 409);
            } elseif (in_array($e->getCode(), [10237, 10239])) {
                return self::respError("404", 404);
            } else {
                return self::respError(["InvalidCategory", 'title' => $in['name'] ?? ""], 422);
            }
        }
        // retrieve the current information about the folder and return it
        $meta = $this->userMeta(Arsse::$user->id);
        $f = ($folder === 0) ? ['id' => null, 'name' => $meta['root']] : Arsse::$db->folderPropertiesGet(Arsse::$user->id, $folder);
        return HTTP::respJson($this->transformCategory($f, $meta['num']), 201);
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
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
    }

    protected function getFeeds(): ResponseInterface {
        $out = [];
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $r) {
            $out[] = $this->transformFeed($r, $meta['num'], $meta['root'], $meta['tz']);
        }
        return HTTP::respJson($out);
    }

    protected function getFeedCounters(): ResponseInterface {
        $out = ['reads' => [], 'unreads' => []];
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $r) {
            $out['reads'][$r['id']] = (int) $r['read'];
            $out['unreads'][$r['id']] = (int) $r['unread'];
        }
        // if there are no subscriptions, ensure the empty arrays are
        //   serialized as objects
        // NOTE: We can be sure that any non-empty arrays will serialize as
        //   objects because zero is never a valid subscription ID
        $out['reads'] = $out['reads'] ?: new \stdClass;
        $out['unreads'] = $out['unreads'] ?: new \stdClass;
        return HTTP::respJson($out);
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
            return self::respError("404", 404);
        }
        return HTTP::respJson($out);
    }

    protected function getFeed(array $path): ResponseInterface {
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        try {
            $sub = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $path[1]);
            return HTTP::respJson($this->transformFeed($sub, $meta['num'], $meta['root'], $meta['tz']));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
    }

    protected function createFeed(array $data): ResponseInterface {
        $properties = [
            'folder' => ($data['category_id'] ?? 1) - 1, 
            'scrape' => (bool) $data['crawler'], 
            'keep_rule' => $data['keeplist_rules'], 
            'block_rule' => $data['blocklist_rules'],
            'username' => $data['username'],
            'password' => $data['password'],
            'user_agent' => $data['user_agent'],
            'cookie'   => $data['cookie'],
        ];
        try {
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, $data['feed_url'], false, $properties);
        } catch (FeedException $e) {
            $msg = [
                10502 => "Fetch404",
                10506 => "Fetch403",
                10507 => "Fetch401",
                10521 => "Fetch404",
                10522 => "FetchFormat",
            ][$e->getCode()] ?? "FetchOther";
            return self::respError($msg, 502);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10235:
                    return self::respError("MissingCategory", 422);
                case 10236:
                    return self::respError("DuplicateFeed", 409);
                default:
                    throw $e;
            }
        }
        return HTTP::respJson(['feed_id' => $id], 201);
    }

    protected function updateFeed(array $path, array $data): ResponseInterface {
        $in = [];
        foreach (self::FEED_META_MAP as $from => $to) {
            if (isset($data[$from])) {
                $in[$to] = $data[$from];
            }
        }
        // Miniflux category IDs start at 1, but our root folder is 0, so we always subtract 1
        if (isset($in['folder'])) {
            $in['folder'] -= 1;
        }
        // Miniflux interprets the empty string as "default User-Agent" rather
        //   than "no User-Agent" as we do; we therefore change the value to
        //   null, which is our value for "default User-Agent"
        if (isset($in['user_agent']) && !strlen($in['user_agent'])) {
            $in['user_agent'] = null;
        }
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $path[1], $in);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10230:
                    $field = $e->getParam("field");
                    $field = ($field === "url") ? "feed_url" : $field; // this case is not encountered in practice because we validate URLs as part of general input validation
                    return self::respError(["InvalidInputValue", 'field' => $field], 422);
                case 10231:
                case 10232:
                    return self::respError("InvalidTitle", 422);
                case 10235:
                    return self::respError("MissingCategory", 422);
                case 10239:
                    return self::respError("404", 404);
                default:
                    throw $e; // @codeCoverageIgnore
            }
        }
        return $this->getFeed($path)->withStatus(201);
    }

    protected function deleteFeed(array $path): ResponseInterface {
        try {
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $path[1]);
            return HTTP::respEmpty(204);
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
    }

    protected function getFeedIcon(array $path): ResponseInterface {
        try {
            $icon = Arsse::$db->subscriptionIcon(Arsse::$user->id, (int) $path[1]);
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
        if (!$icon || !$icon['type'] || !$icon['data']) {
            return self::respError("404", 404);
        }
        return HTTP::respJson($this->transformIcon($icon));
    }

    protected function computeContext(array $query, Context $c): RootContext {
        $c->limit($query['limit'] ?? self::DEFAULT_ENTRY_LIMIT) // NOTE: This does not honour user preferences
            ->offset($query['offset'])
            ->starred($query['starred'])
            ->addedRange($query['after'], $query['before'])
            ->publishedRange($query['published_after'], $query['published_before'])
            ->modifiedRange($query['changed_after'], $query['changed_before'])
            ->articleRange($query['after_entry_id'] ? $query['after_entry_id'] + 1 : null, $query['before_entry_id'] ? $query['before_entry_id'] - 1 : null)
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
        } elseif (in_array($query['order'], ["title", "author"])) {
            return [$query['order'].$desc];
        } else {
            return [self::DEFAULT_ORDER_COL.$desc];
        }
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
            // add the feed objects and tags to each entry
            // NOTE: If ever we implement multiple enclosures, this would be the right place to add them
            for ($a = 0; $a < sizeof($out); $a++) {
                $out[$a]['feed'] = $feeds[$out[$a]['feed_id']];
                $out[$a]['tags'] = Arsse::$db->articleCategoriesGet(Arsse::$user->id, $out[$a]['id']);
            }
        }
        // finally compute the total number of entries matching the query, where necessary
        $count = sizeof($out);
        if ($c->offset || ($c->limit && $count >= $c->limit)) {
            $count = Arsse::$db->articleCount(Arsse::$user->id, (clone $c)->limit(0)->offset(0));
        }
        return ['total' => $count, 'entries' => $out];
    }

    protected function findEntry(int $id, Context $c): array {
        $c = ($c ?? new Context)->article($id);
        $tr = Arsse::$db->begin();
        $meta = $this->userMeta(Arsse::$user->id);
        // find the entry we want; this will throw an exception if the entry is missing
        $entry = Arsse::$db->articleList(Arsse::$user->id, $c, self::ARTICLE_COLUMNS)->getRow();
        // there can fail to be an entry returned without an exception if one specifies a valid feed/category ID and valid entry ID while the entry does not belong to the feed/category
        if (!$entry) {
            throw new ExceptionInput("idMissing", ['id' => $id, 'field' => 'entry']);
        }
        $out = $this->transformEntry($entry, $meta['num'], $meta['tz']);
        // next transform the parent feed of the entry
        $out['feed'] = $this->transformFeed(Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $out['feed_id']), $meta['num'], $meta['root'], $meta['tz']);
        // add the article categories
        $out['tags'] = Arsse::$db->articleCategoriesGet(Arsse::$user->id, $id);
        return $out;
    }

    protected function getEntries(array $query): ResponseInterface {
        try {
            return HTTP::respJson($this->listEntries($query, new Context));
        } catch (ExceptionInput $e) {
            return self::respError("MissingCategory", 400);
        }
    }

    protected function getFeedEntries(array $path, array $query): ResponseInterface {
        $c = (new Context)->subscription((int) $path[1]);
        try {
            return HTTP::respJson($this->listEntries($query, $c));
        } catch (ExceptionInput $e) {
            // FIXME: this should differentiate between a missing feed and a missing category, but doesn't
            return self::respError("404", 404);
        }
    }

    protected function getCategoryEntries(array $path, array $query): ResponseInterface {
        $query['category_id'] = (int) $path[1];
        try {
            return HTTP::respJson($this->listEntries($query, new Context));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
    }

    protected function getEntry(array $path): ResponseInterface {
        try {
            return HTTP::respJson($this->findEntry((int) $path[1], new Context));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
    }

    protected function getFeedEntry(array $path): ResponseInterface {
        $c = (new Context)->subscription((int) $path[1]);
        try {
            return HTTP::respJson($this->findEntry((int) $path[3], $c));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
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
            return HTTP::respJson($this->findEntry((int) $path[3], $c));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
    }

    protected function updateEntry(array $path, array $data): ResponseInterface {
        // NOTE: We decline to implement this functionality because the use
        //   case seems weak and it will probably have odd interactions with
        //   feed updates
        return $this->getEntry($path);
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
        return HTTP::respEmpty(204);
    }

    protected function massRead(Context $c): void {
        Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c->hidden(false));
    }

    protected function markUserByNum(array $path): ResponseInterface {
        // this function is restricted to the logged-in user
        $user = Arsse::$user->propertiesGet(Arsse::$user->id);
        if (((int) $path[1]) !== $user['num']) {
            return self::respError("403", 403);
        }
        $this->massRead(new Context);
        return HTTP::respEmpty(204);
    }

    protected function markFeed(array $path): ResponseInterface {
        try {
            $this->massRead((new Context)->subscription((int) $path[1]));
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
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
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
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
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
    }

    protected function refreshFeed(array $path): ResponseInterface {
        // NOTE: This is a no-op; we simply check that the feed exists
        try {
            Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $path[1]);
        } catch (ExceptionInput $e) {
            return self::respError("404", 404);
        }
        return HTTP::respEmpty(204);
    }

    protected function refreshAllFeeds(): ResponseInterface {
        // NOTE: This is a no-op
        // It could be implemented, but the need is considered low since we use a dynamic schedule always
        return HTTP::respEmpty(204);
    }

    protected function opmlImport(string $data): ResponseInterface {
        try {
            Arsse::$obj->get(OPML::class)->import(Arsse::$user->id, $data);
        } catch (ImportException $e) {
            switch ($e->getCode()) {
                case 10611:
                    return self::respError("InvalidBodyXML", 400);
                case 10612:
                    return self::respError("InvalidBodyOPML", 422);
                case 10613:
                    return self::respError("InvalidImportCategory", 422);
                case 10614:
                    return self::respError("DuplicateImportCategory", 422);
                case 10615:
                    return self::respError("InvalidImportLabel", 422);
            }
        } catch (FeedException $e) {
            return self::respError(["FailedImportFeed", 'url' => $e->getParam("url"), 'code' => $e->getCode()], 502);
        }
        return HTTP::respJson(['message' => Arsse::$lang->msg("API.Miniflux.ImportSuccess")]);
    }

    protected function opmlExport(): ResponseInterface {
        return HTTP::respText(Arsse::$obj->get(OPML::class)->export(Arsse::$user->id), 200, ['Content-Type' => "application/xml"]);
    }

    protected function scrapeEntry(array $path): ResponseInterface {
        try {
            $tr = Arsse::$db->begin();
            $c = (new Context)->article((int) $path[1]);
            $entry = Arsse::$db->articleList(Arsse::$user->id, $c, ["url", "subscription"])->getRow();
            $sub = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $entry['subscription']);
            return HTTP::respJson(['content' => Feed::scrapeSingle($entry['url'], $sub['url'], $sub['user_agent'], $sub['cookie'])]);
        } catch (ExceptionInput $e) {
            return $this->respError("404", 404);
        } catch (FeedException $e) {
            $msg = [
                10502 => "Fetch404",
                10506 => "Fetch403",
                10507 => "Fetch401",
                10521 => "Fetch404",
                10522 => "FetchFormat",
            ][$e->getCode()] ?? "FetchOther";
            return self::respError($msg, 502);
        }
    }

    protected function saveEntry(): ResponseInterface {
        // NOTE: This is a no-op because we do not support any third-party
        //   integrations; Miniflux does not report 404 if no integrations
        //   exist, returning 400 even for fictitious entries
        return self::respError("NoIntegrations", 400);
    }

    protected function flushHistory(): ResponseInterface {
        // NOTE: This is a no-op: we do not track history, and the API doesn't
        //   seem to have a means of displaying it in any case
        return HTTP::respEmpty(202);
    }

    protected function getVersion(): ResponseInterface {
        return HTTP::respJson([
            'version'       => self::VERSION,
            'commit'        => self::COMMIT,
            'build_date'    => self::BUILD_DATE,
            'go_version'    => self::GO_VERSION,
            'compiler'      => "gc",
            'arch'          => php_uname("m"),
            'os'            => php_uname("s"),
            'arsse_version' => Arsse::VERSION,
        ]);
    }

    protected function getIcon(array $path): ResponseInterface {
        try {
            $icon = Arsse::$db->iconPropertiesGet(Arsse::$user->id, (int) $path[1]);
        } finally {
            // Missing icon data is not likely, but may occur for installations
            //  of The Arsse which happen to upgrade directly from 0.7.1
            //   or earlier and immediately use Miniflux
            if (!isset($icon) || !$icon['data']) {
                return self::respError("404", 404);
            }
        }
        return HTTP::respJson($this->transformIcon($icon));
    }

    protected function getIntegrations(): ResponseInterface {
        // NOTE: This is a stub: we do not support integrations
        return HTTP::respJson(['has_integrations' => false]);
    }

    protected function getEnclosure(array $path): ResponseInterface {
        // NOTE: Enclosures currently have no ID and are limited to one per
        //   article, so we're simply using the article ID as the enclosure ID
        //   for now and querying the article's data
        $c = (new Context)->article((int) $path[1]);
        try {
            $entry = Arsse::$db->articleList(Arsse::$user->id, $c, ["id", "media_url", "media_type"])->getRow();
        } finally {
            // Return 404 if either the article with the ID doesn't exist, or
            //   the article has no enclosure
            if (!isset($entry) || !$entry['media_url']) {
                return self::respError("404", 404);
            }
        }
        $meta = $this->userMeta(Arsse::$user->id);
        return HTTP::respJson($this->transformEnclosure($entry, $meta['num']));
    }
}
