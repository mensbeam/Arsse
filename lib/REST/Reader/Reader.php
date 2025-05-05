<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\AbstractContext;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\RootContext;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\HTTP;
use MensBeam\Mime\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Reader extends \JKingWeb\Arsse\REST\AbstractHandler {
    use Common;

    protected const BODY_IGNORE = 0;
    protected const BODY_READ = 1;
    protected const BODY_PARSE= 2;
    protected const LABEL_PATTERN = "/^user\/[^\/]+\/label\/(.+)/";
    protected const STATE_PATTERN = "/^user\/[^\/]+\/state\/com\.google\/(.+)/";
    /** The list of URL matches for calls
     * 
     * An asterisk in a URL is a stand-in for any stream ID. Resources may
     * allow GET or POST or both; entries with "T req" true require a POST
     * token, and those with "Atom" true allow output in the Atom format.
     * 
     * The list of allowed parameters excludes "T" and "output", which are
     * handled specially when input is parsed.
    */
    protected const CALLS = [         // Handler method     GET    POST   T req  Atom   Allowed params
        '/disable-tag'            => ["tagDisable",         false, true,  true,  false, ['s' => V::T_STRING, 't' => V::T_STRING]],
        '/edit-tag'               => ["tagEdit",            false, true,  true,  false, ['i' => V::T_MIXED + V::M_ARRAY, 'a' => V::T_STRING + V::M_ARRAY, 'r' => V::T_STRING + V::M_ARRAY]],
        '/friend/list'            => ["friendsGet",         true,  false, false, false, []],
        '/mark-all-as-read'       => ["streamMark",         false, true,  true,  false, ['s' => V::T_STRING, 'ts' => V::T_STRING]], // 'ts' is actually a date, but it's in an irregular format, so will require special handling
        '/preference/list'        => ["prefsGet",           true,  false, false, false, []],
        '/preference/stream/list' => ["prefsStreamGet",     true,  false, false, false, []],
        '/rename-tag'             => ["tagRename",          false, true,  true,  false, ['s' => V::T_STRING, 't' => V::T_STRING, 'dest' =>V::T_STRING]],
        '/stream/contents'        => ["streamContents",     true,  false, false, true,  ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/stream/contents/*'      => ["streamContents",     true,  false, false, true,  ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/stream/items/contents'  => ["itemContents",       true,  true,  false, true,  ['i' => V::T_STRING + V::M_ARRAY]],
        '/stream/items/count'     => ["itemCount",          true,  false, false, false, ['s' => V::T_STRING, 'a' => V::T_BOOL]],
        '/stream/items/ids'       => ["itemIds",            true,  false, false, false, ['s' => V::T_STRING, 'n' => V::T_INT, 'includeAllDirectStreamIds' => V::T_BOOL, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/subscribed'             => ["subscriptionValid",  true,  false, false, false, ['s' => V::T_STRING]],
        '/subscription/edit'      => ["subscriptionEdit",   false, true,  true,  false, ['ac' => V::T_STRING, 's' => V::T_STRING, 't' => V::T_STRING, 'a' => V::T_STRING + V::M_ARRAY, 'r' => V::T_STRING + V::M_ARRAY]],
        '/subscription/export'    => ["subscriptionExport", true,  false, false, false, []],
        '/subscription/import'    => ["subscriptionImport", false, true,  false, false, []],
        '/subscription/list'      => ["subscriptionList",   true,  false, false, false, []],
        '/subscription/quickadd'  => ["subscriptionAdd",    false, true,  true,  false, ['quickadd' => V::T_STRING]],
        '/tag/list'               => ["tagList",            true,  false, false, false, []],
        '/token'                  => ["tokenCreate",        true,  false, false, false, []],
        '/unread-count'           => ["countsGet",          true,  false, false, false, []],
        '/user-info'              => ["userGet",            true,  false, false, false, []],
    ];
    protected const CONTINUATION_PARAMS = ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE];
    /** A list of state streams which we do not support and will therefore return an empty set when queried */
    protected const UNSUPPORTED_STATES = [
        "broadcast",
        "broadcast-fiends",
        "broadcast-friends-comments", // The Old Reader seems to support this
        "created", // BazQux suggests this existed, but does not itself support it
        "like", // The Old Reader seems to support this
    ];
    /** A list of reserved state names which cannot be used as an article tag */
    protected const RESERVED_STATES = ["read", "kept-unread", "starred", "reading-list"] + self::UNSUPPORTED_STATES;
    protected const OUTPUT_TYPES = [
        "application/json",
        "application/xml",
        "text/xml", // interpreted as Atom
    ];
    protected const FORMAT_MAP = [
        'application/json' => "json",
        'application/xml'  => "xml",
        'text/xml'         => "atom",
    ];
    protected const ACCEPTED_TYPES_OPML = ["application/xml", "text/xml", "text/x-opml"];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $method = strtoupper($req->getMethod());
        $target = parse_url($req->getRequestTarget(), \PHP_URL_PATH);
        // handle OPTIONS requests
        if ($method === "OPTIONS") {
            return $this->handleHttpOptions($target);
        }
        // try to authenticate
        if ($this->authenticate($req)) {
            return $this->challenge(self::respError("401", 401));
        }
        // determine which handler to call
        $func = $this->chooseCall($target, $method);
        if ($func instanceof ResponseInterface) {
            return $func;
        }
        [$func, $params, $reqT, $atomAllowed] = $func;
        // parse body and query arguments (the body is not parsed for OPML import, only read from the request object)
        $bodyMode = $method === "POST" ? ($func !== "subscriptionImport" ? self::BODY_PARSE : self::BODY_READ) : self::BODY_IGNORE;
        [$format, $query, $body, $token] = $this->parseInput($req, $params, $bodyMode);
        // perform content negotiation if a format is not specified in the query
        $format = $format ?? self::FORMAT_MAP[MimeType::negotiate(self::OUTPUT_TYPES, $req->getHeaderLine("Accept")) ?? "application/xml"];
        $format = ($format === "atom" && !$atomAllowed) ? "xml" : $format;
        // check the POST token, if appropriate
        if ($reqT && Arsse::$conf->userSessionEnforced) {
            if (!isset($token)) {
                self::respError("TokenRequired", 400);
            }
            try {
                Arsse::$db->tokenLookup("reader.post", $token, Arsse::$user->id);
            } catch (ExceptionInput $e) {
                return $this->challenge(self::respError("401", 401, ['X-Reader-Google-Bad-Token' => "true"]));
            }
        }
        // handle the request
        try {
            return $this->$func($target, $query, $body, $format);
            // @codeCoverageIgnoreStart
        } catch (AbstractException $e) {
            // if there was any other Arsse exception return 500
            return self::respError($e, 500);
        }
        // @codeCoverageIgnoreEnd
    }

    protected function chooseCall(string $url, string $method) {
        if (strpos($url, "/stream/contents/") === 0) {
            // Stream contents is the one case where the URL is variable
            $url = "/stream/contents/*";
        }
        if (isset(self::CALLS[$url])) {
            [$func, $GET, $POST, $reqT, $atom, $params] = self::CALLS[$url];
            switch ($method) {
                case "GET":
                case "POST":
                    if ($$method) {
                        return [$func, $params, $reqT, $atom];
                    }
                    // no break
                default:
                    $allowed = [];
                    if ($GET) {
                        $allowed[] = "GET";
                    }
                    if ($POST) {
                        $allowed[] = "POST";
                    }
                    return HTTP::respEmpty(405, ['Allow' => implode(", ", $allowed)]);
            }
        } else {
            return HTTP::respEmpty(404);
        }
    }

    protected function handleHttpOptions(string $url) {
        if (strpos($url, "/stream/contents/") === 0) {
            $url = "/stream/contents/*";
        }
        if (isset(self::CALLS[$url])) {
            [$func, $GET, $POST, $params] = self::CALLS[$url];
            $allowed = [];
            if ($GET) {
                $allowed[] = "GET";
            }
            if ($POST) {
                $allowed[] = "POST";
            }
            return HTTP::respEmpty(204, [
                'Allow' => implode(", ", $allowed),
                'Accept' => implode(", ", $url === "/subscription/import" ? self::ACCEPTED_TYPES_OPML : ["x-www-form-urlencoded"]),
            ]);
        } else {
            return HTTP::respEmpty(404);
        }
    }

    /** Extracts body and query input from a request
     * 
     * Returns an indexed array containing three members:
     * 
     * - The requested output format ("json", "xml", "atom", or null)
     * - The used query parameters as an array, with allowed but unused members
     *   set to null or an empty array, as appropriate
     * - The entity body, parsed the same as teh query unless requested otherwise
     */
    protected function parseInput(ServerRequestInterface $req, array $allowed, int $bodyMode): array {
        $format = null;
        $token = null;
        // fill an array with all allowed keys
        foreach ($allowed as $k => $t) {
            $outG[$k] = ($t >= V::M_ARRAY) ? [] : null;
        }
        // parse the query
        $outG = $this->parseQuery(parse_url($req->getRequestTarget(), \PHP_URL_QUERY) ?? "", $allowed, true, false);
        $format = $outG['output'];
        unset($outG['output']);
        // handle the body
        if ($bodyMode === self::BODY_IGNORE) {
            // if we don't care about the body, don't even read it
            $outP = [];
        } else {
            // otherwise read it
            $body = (string) $req->getBody();
            if ($bodyMode === self::BODY_READ) {
                // but return it as-is if so requested (e.g. for OPML import)
                $outP = $body;
            } else {
                $outP = $this->parseQuery($body, $allowed, false, true);
                $token = $outP['T'];
                unset($outP['T']);
            }
        }
        return [$format, $outG, $outP, $token];
    }

    protected function parseQuery(string $query, array $allowed, bool $allowFormat, bool $allowToken): array {
        $out = [];
        // fill an array with all allowed keys
        foreach ($allowed as $k => $t) {
            $out[$k] = ($t >= V::M_ARRAY) ? [] : null;
        }
        if ($allowFormat) {
            $out['output'] = null;
        }
        if ($allowToken) {
            $out['T'] = null;
        }
        // parse the string
        foreach (explode("&", $query) as $q) {
            [$k, $v] = array_pad(explode("=", $q, 2), 2, "");
            $v = urldecode($v);
            if ($k === "output" && $allowFormat && in_array($v, self::FORMAT_MAP)) {
                // handle the "output" parameter which may dictate the format of our output
                $out[$k] = $v;
                continue;
            } elseif ($k === "T" && $allowToken) {
                // handle POST tokens
                $out[$k] = $v;
                continue;
            } elseif (!isset($allowed[$k])) {
                // the parameter is not allowed for this call, so can be ignored
                continue;
            } elseif ($v === "") {
                // if the value is empty, ignore it
                continue;
            }
            $t = $allowed[$k] & ~V::M_ARRAY;
            $a = $allowed[$k] >= V::M_ARRAY;
            if ($a) {
                $out[$k][] = V::normalize($v, $t + V::M_DROP, "unix");
            } else {
                // NOTE: The last value is kept in case of duplicates; this is
                //   what FreshRSS does because it's what PHP does with the
                //   $_GET and $_POST superglobals
                $out[$k] = V::normalize($v, $t + V::M_DROP, "unix");
            }
        }
        return $out;
    }

    /** Converts an item ID (which could be a plain integer or a tag URN) into an internal database ID
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/terminology.html#items
     */
    protected function itemIdDecode($itemId): int {
        if (is_int($itemId)) {
            return $itemId;
        } elseif (is_string($itemId) && preg_match('/^tag:google.com,2005:reader\/item\/([0-9a-fA-F]{16})$/', $itemId, $m)) {
            return hexdec($m[1]);
        } else {
            throw new \Exception("STUB");
        }
    }

    /** Converts an internal database item ID into a Reader tag URN
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/terminology.html#items
     */
    protected function itemIdEncode(int $itemId): string {
        return "tag:google.com,2005:reader/item/".str_pad(dechex($itemId), 16, "0", \STR_PAD_LEFT);
    }

    /** Computes the page size within bounds based on what was requested by the client */
    protected function pageSize(?int $s): int {
        // NOTE: The page size defaults to 20 in BazQux despite being a
        //   required parameter in other implementations; on the other hand
        //   BazQux has a higher limit of 50k, but we'll use the more common
        //   upper bound of 10k since there's no harm in doing so
        return min(max($s, 0) ?: 20, 10000);
    }

    /** Converts a stream identifier into a database context
     * 
     * Because feed streams are identified by URL this procedure my require
     * database activity, but should nevertheless be fast and safe
     * 
     * A null return value indicates a stream which will always return no articles
     * 
     * @param string $stream The stream identifier
     * @param ?AbstractContext $c An existing context to apply the stream to. This may be any kind of context, but if one is supplied, splice streams are forbidden
     * @return Context|UnionContext
     */
    protected function streamContext(?string $stream, ?AbstractContext $c = null): ?AbstractContext {
        $stream = $stream ?? "";
        // We only allow unions for the 's' paramter; anything else would be
        //   too complicated to compute with our current infrastructure
        $unionAllowed = !$c;
        // most contexts will not be union contexts, so we'll assume the common case
        $c = $c ?? new Context;
        if ($stream === "") {
            // NOTE: BazQux and FreshRSS both interpret absence of a stream as
            //   the "reading list" stream (all articles) in at least some
            //   circumstances. We apply this interpretation universally and
            //   leave it to the individual functions to determine whether the
            //   reading list is a valid target
            if (!$c instanceof RootContext) {
                // excluding everything is an empty set
                throw new EmptySetException;
            }
            return $c;
        } elseif (preg_match(self::LABEL_PATTERN, $stream, $m)) {
            return $c->tagName($m[1]);
        } elseif (preg_match(self::STATE_PATTERN, $stream, $m)) {
            if (in_array($m[1], self::UNSUPPORTED_STATES)) {
                if (!$c instanceof RootContext) {
                    // excluding an empty set is a no-op
                    return $c;
                }
                // unsupported states will always be an empty set
                throw new EmptySetException;
            }
            switch ($m[1]) {
                case "read":
                    return $c->unread(false);
                case "kept-unread":
                    return $c->unread(true);
                case "reading-list":
                    if (!$c instanceof RootContext) {
                        // excluding everything is an empty set
                        throw new EmptySetException;
                    }
                    return $c;
                case "starred":
                    return $c->starred(true);
                default:
                    return $c->labelName($m[1]);
            }
        } elseif (preg_match('<^feed/(.+)>', $stream, $m)) {
            // if no subscription is found this will throw an exception
            return $c->subscription(Arsse::$db->subscriptionLookup(Arsse::$user->id, $m[1]));
        } elseif (preg_match('<^splice/(.+)>', $stream, $m)) {
            if (!$unionAllowed) {
                throw new Exception("InvalidSplice");
            }
            // this requires a union context
            $u = new UnionContext();
            foreach (explode("|", $stream) as $s) {
                $u[] = $this->streamContext($s);
            }
            return $u;
        }
        throw new Exception("InvalidStream");
    }

    /** Authenticates the user
     * 
     * As with the rest of The Arsse, pre-authentication with Basic
     * authentication may be required, whereafter the protocol-level
     * authentication may be ignored; otherwise we follow the specification
     * as per FeedHQ
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/terminology.html#authentication
     */
    protected function authenticate(ServerRequestInterface $req): bool {
        if ($req->getAttribute("authenticated", false)) {
            // if HTTP authentication was successfully used, set the expected user ID
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        } elseif (Arsse::$conf->userHTTPAuthRequired || Arsse::$conf->userPreAuth || $req->getAttribute("authenticationFailed", false)) {
            // otherwise if HTTP authentication failed or is required, deny access at the HTTP level
            return false;
        }
        if (isset(Arsse::$user->id) && !Arsse::$conf->userSessionEnforced) {
            // if sessions are not enforced don't even check the login token
            return true;
        } else {
            // otherwise look for the first "GoogleLogin" authorization token and try to authenticate with it
            foreach ($req->getHeader("Authorization") as $h) {
                if (preg_match('/^GoogleLogin\s+auth=(\S+)/', $h, $m)) {
                    try {
                        Arsse::$user->id = Arsse::$db->tokenLookup("reader.login", $m[1])['user'];
                        return true;
                    } catch (ExceptionInput $e) {
                        return false;
                    }
                }
            }
        }
        return false;
    }

    protected function tokenCreate(string $target, array $query, array $body, string $format): ResponseInterface {
        // We create a token with a 30-minute expiry; this is typical for
        //   other Reader implementations and seems to be what the original did
        return HTTP::respText(Arsse::$db->tokenCreate(Arsse::$user->id, "reader.post", null, $this->now()->add(new \DateInterval("PT30M"))));
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#user-info */
    protected function userGet(string $target, array $query, array $body, string $format): ResponseInterface {
        $user = Arsse::$user->id;
        $meta = Arsse::$user->propertiesGet($user);
        return self::respond($format, [
            'userName'            => $user,
            'userEmail'           => "",
            'userId'              => (string) $meta['num'],
            'userProfileId'       => (string) $meta['num'],
            'isBloggerUser'       => false,
            'signupTimeSec'       => V::normalize($this->now(), V::T_INT),
            'isMultiLoginEnabled' => false,
        ]);
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#friend-list */
    protected function friendsGet(string $target, array $query, array $body, string $format): ResponseInterface {
        $user = Arsse::$user->id;
        $meta = Arsse::$user->propertiesGet($user);
        return self::respond($format, [
            'friends' => [
                [
                    'userIds'                 => (string) [$meta['num']],
                    'profileIds'              => (string) [$meta['num']],
                    'contactId'               => '-1',
                    'stream'                  => "user/{$meta['num']}/state/com.google/broadcast",
                    'flags'                   => 1,
                    'displayName'             => $user,
                    'givenName'               => $user,
                    'n'                       => '',
                    'p'                       => '',
                    'hasSharedItemsOnProfile' => false,
                ]
            ]
        ]);
    }
    
    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#preference-list */
    protected function prefsGet(string $target, array $query, array $body, string $format): ResponseInterface {
        return self::respond($format, [
            'prefs' => [
                [
                    'id' => "lhn-prefs",
                    'value' => '{"subscriptions":{"ssa":"true"}}',
                ],
            ],
        ]);
    }
    
    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#preference-stream-list */
    protected function prefsStreamGet(string $target, array $query, array $body, string $format): ResponseInterface {
        return self::respond($format, ['streamprefs' => new \stdClass]);
    }

    /** Deletes a feed label or article tag without deleting the feeds/articles
     *  it is associated with
     * 
     * FeedHQ is unclear about whether it treats feed labels and article tags
     * (apparently a FeedHQ extension) as separate entities. We try to
     * untangle this by accepting either labels or tags when a stream ID is
     * specified with the 's' parameter, and assuming a label when the 't'
     * parameter is specified. Hopefully this is in line with what clients
     * expect, assuming any clients for FeedHQ even still exist
     */
    protected function tagDisable(string $target, array $query, array $body, string $format): ResponseInterface {
        try {
            if (preg_match(self::LABEL_PATTERN, $body['s'] ?? "", $m)) {
                Arsse::$db->tagRemove(Arsse::$user->id, $m[1], true);
            } elseif (preg_match(self::STATE_PATTERN, $body['s'] ?? "", $m)) {
                // the built-in states have special meaning to the server or client and cannot be disabled
                if (in_array($m[1], self::RESERVED_STATES)) {
                    return self::respError(["ReservedState", 'state' => $body['s'], 'operation' => "disable-tag"]);
                }
                Arsse::$db->labelRemove(Arsse::$user->id, $m[1], true);
            } elseif (isset($body['t'])) {
                Arsse::$db->tagRemove(Arsse::$user->id, $body['t'], true);
            } elseif (!isset($body['s']) && !isset($body['t'])) {
                return self::respError(["ParameterRequired", "s"]);
            } else {
                // the $body['t'] case here is unreachable, but we'll cover it in case this changes
                return self::respError(["InvalidStream", $body['s'] ?? "user/-/label/".$body['t']]);
            }
        } catch (ExceptionInput $e) {
            return self::respError($e);
        }
        return HTTP::respText("OK");
    }

    /** Renames a feed label or article tag
     * 
     * Unlike with the disable operation, FeedHQ only operates on feed labels
     * here and not article tags. It takes very little effort to do both,
     * however, so we also allow renaming article tags if they are explicitly
     * specified, in the same manner as the disable operation.
     */
    protected function tagRename(string $target, array $query, array $body, string $format): ResponseInterface {
        // check that the destination name is at least set; we'll check if it
        //   conforms to format requirements later
        if (!isset($body['dest'])) {
            return self::respError(["ParameterRequired", "dest"]);
        }
        try {
            if (preg_match(self::LABEL_PATTERN, $body['s'] ?? "", $m)) {
                // check that the destination is also a label
                if (!preg_match(self::LABEL_PATTERN, $body['dest'], $d)) {
                    return self::respError(["InvalidStream", $body['dest']]);
                }
                Arsse::$db->tagPropertiesSet(Arsse::$user->id, $m[1], ['name' => $d[1]], true);
            } elseif (preg_match(self::STATE_PATTERN, $body['s'] ?? "", $m)) {
                // the built-in states have special meaning to the server or client and cannot be renamed
                if (in_array($m[1], self::RESERVED_STATES)) {
                    return self::respError(["ReservedState", 'state' => $body['s'], 'operation' => "rename-tag"]);
                }
                // check that the destination is also a state
                if (!preg_match(self::STATE_PATTERN, $body['dest'], $d)) {
                    return self::respError(["InvalidStream", $body['dest']]);
                }
                // the built-in states have special meaning to the server or client and cannot be used as targets
                if (in_array($d[1], self::RESERVED_STATES)) {
                    return self::respError(["ReservedState", 'state' => $body['dest'], 'operation' => "rename-tag"]);
                }
                Arsse::$db->labelPropertiesSet(Arsse::$user->id, $m[1], ['name' => $d[1]], true);
            } elseif (isset($body['t'])) {
                // check that the destination is also a label
                if (!preg_match(self::LABEL_PATTERN, $body['dest'], $d)) {
                    return self::respError(["InvalidStream", $body['dest']]);
                }
                Arsse::$db->tagPropertiesSet(Arsse::$user->id, $body['t'], ['name' => $d[1]], true);
            } elseif (!isset($body['s']) && !isset($body['t'])) {
                return self::respError(["ParameterRequired", "s"]);
            } else {
                // the $body['t'] case here is unreachable, but we'll cover it in case this changes
                return self::respError(["InvalidStream", $body['s'] ?? "user/-/label/".$body['t']]);
            }
        } catch (ExceptionInput $e) {
            return self::respError($e);
        }
        return HTTP::respText("OK");
    }

    
    /** @see https://github.com/feedhq/feedhq/blob/65f4f04b4e81f4911e30fa4d4014feae4e172e0d/feedhq/reader/views.py#L284 */
    protected function countsGet(string $target, array $query, array $body, string $format): ResponseInterface {
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id);
        $out = [];
        $total = 0;
        $ts = null;
        $summary = [];
        $tags = [];
        // process each subscription, keeping a basic summary for tags
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $sub) {
            $date = $sub['article_modified'];
            $unread = (int) $sub['unread'];
            $out[] = [
                'id'                      => "feed/".$sub['url'],
                'count'                   => $unread,
                'newestItemTimestampUsec' => Date::transform($date, "unix", "sql")."000000",
            ];
            // add the count and date to the summary
            $summary[$sub['id']] = ['count' => $unread, 'ts' => $date];
            // add to the grand total
            $total += $unread;
            // overwrite the global date if appropriate
            $ts = max($ts, $date);
        }
        // aggregate information on tags
        foreach (Arsse::$db->tagSummarize(Arsse::$user->id) as $tag) {
            if (!isset($tags[$tag['name']])) {
                $tags[$tag['name']] = ['count' => 0, 'ts' => null];
            }
            $tags[$tag['name']]['count'] += $summary[$tag['subscription']]['count'];
            $tags[$tag['name']]['ts'] = max($tags[$tag['id']]['ts'], $summary[$tag['subscription']]['ts']);
        }
        // add tags to output
        foreach ($tags as $name => $data) {
            $out[] = [
                'id'                      => "user/{$meta['num']}/label/$name",
                'count'                   => $data['count'],
                'newestItemTimestampUsec' => Date::transform($data['ts'], "unix", "sql")."000000",
            ];
        }
        // add "reading list" (all articles) to output
        $out[] = [
            'id' => "user/{$meta['num']}/state/com.google/reading-list",
            'count' => $total,
            'newestItemTimestampUsec' => Date::transform($ts, "unix", "sql")."000000",
        ];
        // return the whole list
        return self::respond($format, $out);
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-items-count */
    protected function itemCount(string $target, array $query, array $body, string $format): ResponseInterface {
        $out = "";
        // convert the stream ID to a context
        $c = $this->streamContext($query['s']);
        try {
            $tr = Arsse::$db->begin();
            // get the count of articles matched by the context
            $out .= Arsse::$db->articleCount(Arsse::$user->id, $c);
            // if the most recent date is requested as well, jump through some hoops to get it
            if ($query['a']) {
                $c->limit(1);
                $date = Arsse::$db->articleList(Arsse::$user->id, $c, ["modified_date"], ["modified_date desc"])->getValue();
                $out."#".Date::transform($date, "F j, Y", "sql");
            }
        } catch (ExceptionInput $e) {
            // TODO: What do we do about errors?
        }
        return HTTP::respText($out);
    }

    /** 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-items-ids
     * @see https://github.com/bazqux/bazqux-api?tab=readme-ov-file#item-ids
     * @see https://github.com/mihaip/google-reader-api/blob/master/wiki/ApiStreamItemsIds.wiki */
    protected function itemIds(string $target, array $query, array $body, string $format): ResponseInterface {
        $out = [];
        $latest = null;
        $tr = Arsse::$db->begin();
        foreach ($this->articleQuery($query, ["id", 'edition', "modified_date", "subscription_url"]) as $i) {
            if ($query['includeAllDirectStreamIds']) {
                $streams = array_merge(["feed/".$i['subscription_url']], array_map(function($v) {
                    return "user/-/state/com.google/$v";
                }, Arsse::$db->articleLabelsGet(Arsse::$user->id, $i['id'], true)));
            } else {
                $streams = [];
            }
            $out[] = [
                'id' => (int) $i['id'],
                'timestampUsec' => ((int) V::normalize($i['modified_date'], V::T_DATE, "sql"))."000000",
                'directStreamIds' => $streams,
            ];
            $latest = max($latest, (int) $i['edition']);
        }
        $out = ['itemRefs' => $out];
        if (sizeof($out['itemRefs']) === $this->pageSize($query['n'])) {
            // there are probably more items, so we construct a continuation string
            $out['continuation'] = $this->computeContinuation($query, $latest);
        } 
        return self::respond($format, $out);
    }

    protected function articleQuery(array &$query, array $columns): Result {
        $asc = $query['r'] !== "o";
        // parse the continuation string, if any
        if ($query['c']) {
            if (!$ct = @base64_decode($query['c'], true)) {
                throw new Exception("InvalidContinuation");
            }
            // replace the query data with the continuation data; a user
            //   might modify parts of the query to be in conflict with the
            //   continuation, so we simply take whatever is inside the
            //   continuation as authoritative; this ensures that constructing
            //   a new string for the next page later is accurate
            $query = $this->parseQuery($ct, self::CONTINUATION_PARAMS, false, false);
        }
        $c = $this->streamContext($query['s'] ?? "");
        // streams can be refined by adding an AND condition with 'it' 
        //   and/or an AND NOT condition with 'xt'
        if ($query['it']) {
            $this->streamContext($query['it'], $c);
        }
        if ($query['xt']) {
            $this->streamContext($query['it'], $c->not);
        }
        // fairly typical time-based constraits can also be applied
        $c->modifiedRange($query['ot'], $query['nt']);
        // the 'i' parameter is only valid in continuations and is our page anchor
        $c->editionRange($asc ? $query['i'] : null, $asc ? null : $query['i']);
        // pagination is always applied
        $c->limit($this->pageSize($query['n']));
        // sorting by edition gives us a simple, chronological way of having
        //   stable pagination
        $sort = $asc ? "edition" : "edition desc";
        // perform the query
        return Arsse::$db->articleList(Arsse::$user->id, $c, $columns, [$sort]);
    }

    protected function computeContinuation(array $query, int $anchor): string {
        // blank out parameters which are defaults or not necessary
        if ($query['r'] !== "o") {
            $query['r'] = null;
        }
        if ($query['n'] === 20 || $query['n'] < 1) {
            $query['n'] = null;
        }
        unset($query['c'], $query['i']);
        // either increment or decrement our anchor depending on sort order;
        //   this modification has to be made somewhere (context ranges are
        //   inclusive), so we make it here
        if ($query['r']) {
            $anchor--;
        } else {
            $anchor++;
        }
        // strip any null values
        $query = array_filter($query, function($v) {
            return isset($v);
        });
        // sort by key for consistency
        ksort($query);
        // add our anchor
        $query['i'] = $anchor;
        // return the string as base64
        return base64_encode(implode("&", $query));
    }

    protected static function respond(string $format, array $data, int $status = 200, array $headers = []): ResponseInterface {
        assert(in_array($format, ["json", "xml", "atom"]), new \Exception("Invalid format passed for output"));
        if ($format === "xml") {
            $d = new \DOMDocument("1.0", "utf-8");
            $d->appendChild(self::makeXML($data, $d));
            return HTTP::respXml($d->saveXML($d->documentElement, \LIBXML_NOEMPTYTAG));
        } elseif ($format === "atom") {
            throw new \Exception("Atom output not yet implemented");
        } else {
            return HTTP::respJson($data, $status, $headers);
        }
    }

    /** Formats data as XML output according to how FeedHQ does it
     * 
     * @see https://github.com/feedhq/feedhq/blob/65f4f04b4e81f4911e30fa4d4014feae4e172e0d/feedhq/reader/renderers.py#L48
     */
    protected static function makeXML(iterable $data, \DOMDocument $d): \DOMElement {
        // this is a very simplistic check for an indexed array;
        //   it would not pass muster in the face of generic data,
        //   but we'll assume our code produces only well-ordered
        //   indexed arrays
        $object = is_object($data) || !isset($data[0]);
        $p = $d->createElement($object ? "object" : "list");
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $pp = $d->createElement("string", $v);
            } elseif (is_numeric($v)) {
                $pp = $d->createElement("number", (string) $v);
            } elseif (is_array($v) || is_object($v)) {
                $pp = self::makeXML($v, $d);
            } else {
                throw new \Exception("Unsupported type for XML output"); // @codeCoverageIgnore
            }
            if ($object) {
                $pp->setAttribute("name", $k);
            }
            $p->appendChild($pp);
        }
        return $p;
    }
}