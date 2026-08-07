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
use JKingWeb\Arsse\Context\ExclusionContext;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Misc\URL;
use MensBeam\Mime\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Reader extends \JKingWeb\Arsse\REST\AbstractHandler {
    use Common;

    protected const MODE_FRESHRSS = 1;
    protected const MODE_FEEDHQ = 2;
    protected const BODY_IGNORE = 0;
    protected const BODY_READ = 1;
    protected const BODY_PARSE= 2;
    protected const LABEL_PATTERN = "/^user\/[^\/]+\/label\/(.+)/";
    protected const STATE_PATTERN = "/^user\/[^\/]+\/state\/([^\/]+\/.+)/";
    protected const SUBSCRIPTION_PATTERN = "/^feed\/(\d+)$/";
    protected const FEED_PATTERN = "/^feed\/(?i)(https?:\/\/.+)/";
    /** The list of all known parameters */
    protected const ALLOWED = [
        "s",                         // a stream ID
        "t",                         // a user-supplied title when subscribing to a feed with the subscript/edit route; when operating on a label/tag, its bare name (not in stream format; this appears to be a FeedHQ extension)
        "i",                         // an item to select when assigning labels/tags or when retriving item contents
        "a",                         // when assigning states or labels/tags, an assignment to add; for the stream/items/count route, appends the modified date of the latest item when set to "true"
        "r",                         // when assigning states or labels/tags, an assignment to remove; when retrieving stream contents "r=o" specifies reverse (ascending) order
        "ts",                        // a cut-off timestamp for the mark-all-as-read route; items modified after this time are not marked as read
        "dest",                      // the "destination stream" i.e. the new name of a label/tag when renaming, in stream format
        "n",                         // number of items per page when retrieving stream contents
        "c",                         // "continuation" string for pagination when retrieving stream contents; we implement this as a base64-encoded query string for retrieving the next page starting with a given edition ID
        "xt",                        // an "exclusion term" when retrieving stream contents i.e. s=x&xt=y means "x AND NOT y"
        "it",                        // an "inclusion term" when retrieving stream contents i.e. s=x&it=y means "x AND y"
        "ot",                        // oldest timestamp to select when retrieving stream contents; we use the modified date
        "nt",                        // newest timestamp to select when retrieving stream contents; we use the modified date
        "ac",                        // an action for the subscription/edit route, either "subscribe", "unsubscribe", or "edit"
        "quickadd",                  // a stream ID or bare URL for a feed to subscribe to in the subscription/quickadd route
    ];
    /** The list of URL matches for calls
     * 
     * An asterisk in a URL is a stand-in for any stream ID. Resources may
     * allow GET or POST or both; entries with "T req" true require a POST
     * token, and those with "Atom" true allow output in the Atom format.
     * 
     * The list of allowed parameters excludes "T" and "output", which are
     * handled specially when input is parsed.
     * 
     * NOTE: The /stream/contents route ought not to allow POST, but the
     * Newsflash client requires this in order to function.
    */
    protected const CALLS = [         // Handler method     GET    POST   T req  Atom   Allowed params
        '/disable-tag'            => ["tagDisable",         false, true,  true,  false, ['s' => V::T_STRING, 't' => V::T_STRING]],
        '/edit-tag'               => ["tagEdit",            false, true,  true,  false, ['i' => V::T_MIXED + V::M_ARRAY, 'a' => V::T_STRING + V::M_ARRAY, 'r' => V::T_STRING + V::M_ARRAY]],
        '/friend/list'            => ["friendsGet",         true,  false, false, false, []],
        '/mark-all-as-read'       => ["streamMark",         false, true,  true,  false, ['s' => V::T_STRING, 'ts' => V::T_STRING]], // 'ts' is actually a datetime, but it's in an irregular format, so will require special handling
        '/preference/list'        => ["prefsGet",           true,  false, false, false, []],
        '/preference/stream/list' => ["prefsStreamGet",     true,  false, false, false, []],
        '/rename-tag'             => ["tagRename",          false, true,  true,  false, ['s' => V::T_STRING, 't' => V::T_STRING, 'dest' =>V::T_STRING]],
        '/stream/contents'        => ["streamContents",     true,  true,  false, true,  ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/stream/contents/*'      => ["streamContents",     true,  true,  false, true,  ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/stream/items/contents'  => ["itemContents",       true,  true,  false, true,  ['i' => V::T_STRING + V::M_ARRAY]],
        '/stream/items/count'     => ["itemCount",          true,  false, false, false, ['s' => V::T_STRING, 'a' => V::T_BOOL]],
        '/stream/items/ids'       => ["itemIds",            true,  false, false, false, ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
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
    /** The parameters encoded in a continuation string, with their types */
    protected const CONTINUATION_PARAMS = ['s' => V::T_STRING, 'r' => V::T_STRING, 'n' => V::T_INT, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE, 'i' => V::T_INT];
    /** The list of known state names */
    protected const KNOWN_STATES = [
        "com.google/read",
        "com.google/unread",
        "com.google/kept-unread",
        "com.google/starred",
        "com.google/reading-list",
        "org.freshrss/main", // FreshRSS uses this to mean "the reading list plus hidden items"
        "com.google/broadcast",
        "com.google/broadcast-fiends",
        "com.google/broadcast-friends-comments", // The Old Reader seems to support this
        "com.google/created", // BazQux suggests this existed, but does not itself support it
        "com.google/like", // The Old Reader seems to support this
        "org.freshrss/important",
    ];
    /** A list of state streams which we do not support and will therefore return an empty set when queried */
    protected const UNSUPPORTED_STATES = [
        "com.google/broadcast",
        "com.google/broadcast-fiends",
        "com.google/broadcast-friends-comments",
        "com.google/created",
        "com.google/like",
        "org.freshrss/important",
    ];
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

    /** @var ServerRequestInterface */
    protected $request;
    protected $mode;

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $method = strtoupper($req->getMethod());
        $target = parse_url($req->getRequestTarget(), \PHP_URL_PATH);
        // determine which handler to call
        $func = $this->chooseCall($target, $method);
        if ($func instanceof ResponseInterface) {
            return $func;
        }
        // check authentication
        if ($this->shouldChallenge($req)) {
            return self::respError("401", 401);
        } elseif (!$this->authenticate($req)) {
            return $this->challenge(self::respError("401", 401));
        }
        // save the request in case we need it later 
        $this->request = $req;
        // parse body and query arguments (the body is not parsed for OPML import, only read from the request object)
        [$func, $params, $reqT, $atomAllowed] = $func;
        $bodyMode = $method === "POST" ? ($func !== "subscriptionImport" ? self::BODY_PARSE : self::BODY_READ) : self::BODY_IGNORE;
        [$format, $query, $body, $token] = $this->parseInput($req, $params, $bodyMode);
        // perform content negotiation if a format is not specified in the query
        $format = $format ?? self::FORMAT_MAP[MimeType::negotiate(self::OUTPUT_TYPES, $req->getHeaderLine("Accept")) ?? "application/xml"];
        $format = ($format === "atom" && !$atomAllowed) ? "xml" : $format;
        // check the POST token, if appropriate
        if ($reqT && Arsse::$conf->userSessionEnforced && !$this->tokenCheck($token)) {
            return self::respError("401", 401, ['X-Reader-Google-Bad-Token' => "true"]);
        }
        // handle the request
        try {
            return $this->$func($target, $query, $body, $format);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            // Reader exceptions imply bad input, and thus a 400 error
            return self::respError([$e->getSymbol(), ...$e->getParams()], 400);
        } catch (ExceptionInput $e) {
            // input exceptions also imply a 400 error
            return self::respError($e, 400);
        } catch (AbstractException $e) {
            // any other Arsse exception should yield 500
            return self::respError($e, 500);
        } finally {
            unset($this->request);
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
                    if ($method === "OPTIONS") {
                        return HTTP::respEmpty(204, [
                            'Allow' => implode(", ", $allowed),
                            'Accept' => implode(", ", $url === "/subscription/import" ? self::ACCEPTED_TYPES_OPML : ["x-www-form-urlencoded"]),
                        ]);
                    } else {
                        return HTTP::respEmpty(405, ['Allow' => implode(", ", $allowed)]);
                    }
            }
        } else {
            return HTTP::respEmpty(404);
        }
    }

    protected function origin(): string {
        $p = $this->request->getServerParams();
        $scheme = ($p['HTTPS'] ?? "") ? "https" : "http";
        $host = $p['HTTP_HOST'] ?? $p['SERVER_NAME'] ?? "";
        $port = $p['SERVER_PORT'] ?? "";
        return URL::normalize("$scheme://$host:$port");
    }

    /** Extracts body and query input from a request
     * 
     * Returns an indexed array containing three members:
     * 
     * - The requested output format ("json", "xml", "atom", or null)
     * - The used query parameters as an array, with allowed but unused members
     *   set to null or an empty array, as appropriate
     * - The entity body, parsed the same as the query unless requested otherwise
     */
    protected function parseInput(ServerRequestInterface $req, array $allowed, int $bodyMode): array {
        $token = null;
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
        // fill an array with all allowed keys
        $out = array_fill_keys(self::ALLOWED, null);
        if ($allowFormat) {
            $out['output'] = null;
        }
        if ($allowToken) {
            $out['T'] = null;
        }
        // ensure any array-type parameters (this differs by call) are arrays
        foreach ($allowed as $k => $t) {
            if ($t >= V::M_ARRAY) {
                $out[$k] = [];
            }
        }
        // parse the string
        foreach (explode("&", $query) as $q) {
            [$k, $v] = array_pad(explode("=", $q, 2), 2, "");
            $v = rawurldecode($v);
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
     * @param int|string $itemId
     */
    protected function itemIdDecode($itemId): int {
        if (is_int($itemId)) {
            return $itemId; // @codeCoverageIgnore
        } elseif (is_string($itemId) && preg_match('/^tag:google.com,2005:reader\/item\/([0-7][0-9a-fA-F]{15}|[0-9a-fA-F]{1,15})$/', $itemId, $m)) {
            // NOTE: Reader IDs are signed, but because the database will
            //   never use negative IDs, we can safely reject negative IDs and
            //   save ourselves some complexity in dealing with signed values
            // NetNewsWire will send unpadded tag IDs, so we accept these as
            //   well in order for it to work
            $out = hexdec($m[1]);
            if ($out) {
                // zero is also an invalid database ID, so only return if the value is not zero
                return $out;
            }
        } elseif (is_string($itemId) && preg_match('/^[0-7][0-9A-Fa-f]{15}$/', $itemId)) {
            // Supposedly Reeder sometimes sends item IDs in this format
            // See https://www.davd.io/posts/2025-02-05-reimplementing-google-reader-api-in-2025/#item-ids
            return hexdec($itemId);
        } elseif (is_string($itemId) && preg_match('/^[0-9]+$/', $itemId)) {
            return (int) $itemId;
        }
        throw new Exception("InvalidItemId", $itemId);
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

    /** Creates a sort ID, which is an eight-nybble hexdecimal string */
    protected function makeSortId(int $id): string {
        return str_pad(dechex($id), 8, "0", \STR_PAD_LEFT);
    }

    /** Converts a set of stream identifiers into a database context
     * 
     * Because feed streams are identified by URL this procedure my require
     * database activity, but should nevertheless be fast and safe
     * 
     * The three stream identifiers will ultimately be converted to an SQL
     * filter condition analogous to ($stream AND $include AND NOT $exclude)
     * 
     * A null return value indicates a stream which will always return no articles
     * 
     * @param ?string $stream The base stream identifier
     * @param ?string $include The filter stream identifier
     * @param ?string $exclude The exclusion stream identifier
     * @param Context|ExclusionContext|null $context An existing context to apply the stream to. This should be used only when populating splice streams
     * @return Context|ExclusionContext
     */
    protected function streamContext(?string $stream, ?string $include = null, ?string $exclude = null, ?AbstractContext $context = null): ?AbstractContext {
        $splice = isset($context);
        $context = $context ?? new Context;
        $stream = $stream ?? "";
        if ($stream === "") {
            // NOTE: BazQux and FreshRSS both interpret absence of a stream as
            //   the "reading list" stream (all articles) in at least some
            //   circumstances. We apply this interpretation universally and
            //   leave it to the individual functions to determine whether to
            //   reject requests without an explicit stream
            $stream = "user/-/state/com.google/reading-list";
        }
        foreach (["stream", "include", "exclude"] as $which) {
            $s = $$which;
            if ($s === null) {
                continue;
            }
            $not = $which === "exclude";
            $c = $not ? $context->not : $context;
            if (preg_match(self::STATE_PATTERN, $s, $m)) {
                // NOTE: It is possible to specify both a boolean stream and have
                //   an inverse boolean condition ("unread AND read"), so we must
                //   account for this where it can happen as it is not
                //   representable in a context object
                switch ($m[1]) {
                    case "com.google/read":
                        if ($c->unread === true) {
                            return null;
                        }
                        $c->unread(false);
                        break;
                    case "com.google/unread":
                    case "com.google/kept-unread":
                        if ($c->unread === false) {
                            return null;
                        }
                        $c->unread(true);
                        break;
                    case "com.google/reading-list":
                    case "org.freshrss/main":
                        if ($c instanceof ExclusionContext) {
                            // excluding everything is an empty set
                            return null;
                        }
                        break;
                    case "com.google/starred":
                        $c->starred(true);
                        break;
                    default:
                        if (in_array($m[1], self::UNSUPPORTED_STATES)) {
                            if ($c instanceof Context) {
                                // unsupported states will always be an empty set
                                return null;
                            }
                        }
                }
            } elseif (preg_match(self::LABEL_PATTERN, $s, $m)) {
                // Reader labels can be applied to either feeds or articles, so
                //   we must select for both; the logic here also handles
                //   filtering for multiple labels (x AND y) seamlessly
                // For a splice with multiple labels we need to convert calls
                //   to tagName and labelName into tagNames and LabelNames so
                //   the result is an IN() condition rather than an AND
                $g = $c->orGroups;
                if (!$splice || !$g) {
                    $cc = (new Context)->tagName($m[1])->labelName($m[1]);
                } else {
                    $cc = array_pop($g);
                    if ($cc->tagName() && $cc->labelName()) {
                        $cc->tagNames([$cc->tagName, $m[1]]);
                        $cc->labelNames([$cc->labelName, $m[1]]);
                        $cc->tagName(null);
                        $cc->labelName(null);
                    } else {
                        $t = $cc->tagNames;
                        $l = $cc->labelNames;
                        $t[] = $m[1];
                        $l[] = $m[1];
                        $cc->tagNames($t);
                        $cc->labelNames($l);
                    }
                }
                $g[] = $cc;
                $c->orGroups($g);
            } elseif (preg_match(self::SUBSCRIPTION_PATTERN, $s, $m) || preg_match(self::FEED_PATTERN, $s, $m)) {
                if (!is_numeric($m[1])) {
                    // if no subscription is found this will throw an exception
                    $m[1] = Arsse::$db->subscriptionLookup(Arsse::$user->id, $m[1]);
                }
                if ($c->subscription()) {
                    $sub = $c->subscription;
                    if ($sub === (int) $m[1]) {
                        // the same stream twice is a no-op
                        break;
                    } elseif ($splice) {
                        // different subscriptions in a splice can be unioned
                        $c->subscriptions([$c->subscription, $m[1]]);
                        $c->subscription(null);
                    } else {
                        // different subscriptions outside of a splice is a logical contradiction
                        return null;
                    }
                } elseif ($splice && $c->subscriptions()) {
                    // add to the existing union of subscriptions in the splice
                    $u = $c->subscriptions;
                    $u[] = $m[1];
                    $c->subscriptions($u);
                } else {
                    $c->subscription((int) $m[1]);
                }
            } elseif (preg_match('<^splice/(.+)>', $s, $m)) {
                // splice streams are a union of multiple streams
                $cc = new Context;
                $terms = explode("|", $m[1]);
                $errors = 0;
                foreach ($terms as $s) {
                    // keep track of how many empty sets are returned
                    if (!$this->streamContext($s, null, null, $cc)) {
                        $errors++;
                    }
                }
                if ($errors == sizeof($terms)) {
                    // we found only empty sets, so we return an empty set
                    return null;
                }
                $g = $c->orGroups;
                $g[] = $cc;
                $c->orGroups($g);
            } else {
                throw new Exception("InvalidStream", $s);
            }
        }
        return $context;
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

    protected function tokenCheck(?string $token): bool {
        if ($this->mode === self::MODE_FRESHRSS) {
            if (!isset($token) || $token === "" || $token === "x") {
                // Various FreshRSS clients do not send any token at all; Reeder simply sends "x"
                return true;
            }
            // remove any trailing newline from the token; clients are
            //   inconsistent in stripping the one added in FreshRSS mode
            $token = rtrim($token, "\n");
        }
        try {
            Arsse::$db->tokenLookup("reader.post", $token ?? "", Arsse::$user->id);
            return true;
        } catch (ExceptionInput $e) {
            return false;
        }
    }

    protected function tokenCreate(string $target, array $query, array $body, string $format): ResponseInterface {
        $token = null;
        // Contrary to the original Reader, FreshRSS creates POST tokens which
        //   never expire, and some implementations (such as Newsflash) assume
        //   therefore that tokens never expire and never re-authenticate; as a
        //   result we re-use existing tokens if one is requested, to avoid
        //   cluttering the database if there are implementations which do 
        //   re-authenticate regularly; even FeedHQ clients such as Vienna seem
        //   to expect POST tokens never to expire
        $row = Arsse::$db->tokenList(Arsse::$user->id, "reader.post")->getRow();
        if ($row) {
            $token = $row['id'];
        } else {
            // FreshRSS creates 57-character tokens (using "Z" for padding),
            //   and at least one source claims this is required, so we do
            //   the same, but with far less padding
            $token = base64_encode(random_bytes(42))."Z";
            Arsse::$db->tokenCreate(Arsse::$user->id, "reader.post", $token);
        }
        // Note that the newline at the end of the response is required by at
        //   least some FreshRSS clients (again such as Newsflash) which strip
        //   the last character from the response before saving the token in
        //   their database; Vienna in FeedHQ mode expects no newline, however
        if ($this->mode === self::MODE_FRESHRSS) {
            return HTTP::respText("$token\n");
        } else {
            return HTTP::respText("$token");
        }
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
        // NOTE: This is not implemented by FreshRSS at all
        $user = Arsse::$user->id;
        $meta = Arsse::$user->propertiesGet($user);
        return self::respond($format, [
            'friends' => [
                [
                    'userIds'                 => [(string) $meta['num']],
                    'profileIds'              => [(string) $meta['num']],
                    'contactId'               => "-1",
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

    /** Deletes a feed/article tag/label without deleting the feeds/articles
     *  it is associated with
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#disable-tag
     */
    protected function tagDisable(string $target, array $query, array $body, string $format): ResponseInterface {
        $name = "";
        if (preg_match(self::LABEL_PATTERN, $body['s'] ?? "", $m)) {
            $name = $m[1];
        } elseif (isset($body['t'])) {
            $name = $body['t'];
        } elseif (!isset($body['s']) && !isset($body['t'])) {
            return self::respError(["ParameterRequiredOneOfTwo", "s", "t"]);
        } else {
            // the $body['t'] case here is unreachable, but we'll cover it in case this changes
            return self::respError(["InvalidStream", $body['s'] ?? "user/-/label/".$body['t']]);
        }
        $success = true;
        // first try removing the article label with the name, which is more
        //   likely to fail because they are apt to be used less frequently
        // NOTE: FreshRSS does not do things this way, instead trying to remove
        //   a matching feed tag or then a matching article label, and stopping
        //   once one succeeds 
        try {
            Arsse::$db->labelRemove(Arsse::$user->id, $name, true);
        } catch (ExceptionInput $e) {
            // merely make note of failure; only if feed tag removal also fails
            //   is this actually an error
            $success = false;
        }
        try {
            Arsse::$db->tagRemove(Arsse::$user->id, $name, true);
        } catch (ExceptionInput $e) {
            // report an error if removing the article label also failed
            if (!$success) {
                return self::respError($e);
            }
        }
        return HTTP::respText("OK");
    }

    /** Renames a tag for a feed or a label for an article (or both)
     * 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#rename-tag
     */
    protected function tagRename(string $target, array $query, array $body, string $format): ResponseInterface {
        $old = "";
        $new = "";
        if (!isset($body['dest'])) {
            return self::respError(["ParameterRequired", "dest"]);
        } elseif (preg_match(self::LABEL_PATTERN, $body['dest'], $d)) {
            $new = $d[1];
        } else {
            return self::respError(["InvalidStream", $body['dest']]);
        }
        if (!isset($body['s']) && !isset($body['t'])) {
            return self::respError(["ParameterRequiredOneOfTwo", "s", "t"]);
        } elseif (isset($body['s']) && preg_match(self::LABEL_PATTERN, $body['s'], $d)) {
            $old = $d[1];
        } elseif (isset($body['t'])) {
            $old = $body['t'];
        } else {
            return self::respError(["InvalidStream", $body['s']]);
        }
        // we must rename both the feed tag and article label; it is not an
        //   error if only one fails
        // NOTE: FreshRSS does not do things this way, instead trying to rename
        //   a matching feed tag or then a matching article label, and stopping
        //   once one succeeds 
        $success = true;
        try {
            Arsse::$db->labelPropertiesSet(Arsse::$user->id, $old, ['name' => $new], true);
        } catch (ExceptionInput $e) {
            // merely make note of failure; only if feed tag renaming also
            //   fails is this actually an error
            $success = false;
        }
        try {
            Arsse::$db->tagPropertiesSet(Arsse::$user->id, $old, ['name' => $new], true);
        } catch (ExceptionInput $e) {
            // report an error if renaming the article label also failed
            if (!$success) {
                return self::respError($e);
            }
        }
        return HTTP::respText("OK");
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#tag-list */
    protected function tagList(string $target, array $query, array $body, string $format): ResponseInterface {
        // NOTE: FreshRSS acts very differently from how FeedHQ seemed to,
        //   here. Feed tags and article labels are treated as distinct
        //   objects and are listed separately. It also includes unread
        //   counts for article labels (but not states or feed tags)
        $meta = Arsse::$user->propertiesGet(Arsse::$user->id);
        $sortId = 0;
        // start with the states FreshRSS always includes
        $out = [
            ['id' => "user/{$meta['num']}/state/com.google/starred",      'sortid' => $this->makeSortId($sortId++)],
            ['id' => "user/{$meta['num']}/state/com.google/reading-list", 'sortid' => $this->makeSortId($sortId++)],
            ['id' => "user/{$meta['num']}/state/org.freshrss/main",       'sortid' => $this->makeSortId($sortId++)],
            ['id' => "user/{$meta['num']}/state/org.freshrss/important",  'sortid' => $this->makeSortId($sortId++)],
        ];
        // add all the feed tags (what Reader calls labels) which have associations to feeds
        foreach (Arsse::$db->tagList(Arsse::$user->id, false) as $t) {
            $out[] = [
                'id' => "user/{$meta['num']}/label/".$t['name'],
                'sortid' => $this->makeSortId($sortId++),
                'type' => "folder",
            ];
        }
        // add all the article labels which have associations to articles
        foreach (Arsse::$db->labelList(Arsse::$user->id, false) as $l) {
            $out[] = [
                'id' => "user/{$meta['num']}/label/".$l['name'],
                'sortid' => $this->makeSortId($sortId++),
                'type' => "tag",
                'unread_count' => ((int) $l['articles']) - ((int) $l['read']),
            ];
        }
        return $this->respond($format, ['tags' => $out]);
    }

    /** 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#edit-tag
     * @see https://github.com/bazqux/bazqux-api?tab=readme-ov-file#tagging-items
     * @see https://raw.githubusercontent.com/mihaip/google-reader-api/refs/heads/master/wiki/ApiEditTags.wiki */
    protected function tagEdit(string $target, array $query, array $body, string $format): ResponseInterface {
        if (!$body['i']) {
            return self::respError(["ParameterRequired", "i"]);
        } elseif (!$body['a'] && !$body['r']) {
            return self::respError(["ParameterRequiredOneOfTwo", "a", "r"]);
        }
        $c = new Context;
        // add the items to the context; 
        $c->articles(array_map(function($v) {
            return $this->itemIdDecode($v);
        }, $body['i']));
        $tr = Arsse::$db->begin();
        // apply each state or label in the order they appear, additions first
        foreach (['a' => $body['a'], 'r' => $body['r']] as $op => $set) {
            foreach ($set as $s) {
                if (preg_match(self::LABEL_PATTERN, $s, $m)) {
                    $name = $m[1];
                    if (!isset($labels)) {
                        // get the list of currently extant labels so we know when we need to add one
                        $labels = array_column(iterator_to_array(Arsse::$db->labelList(Arsse::$user->id, true)), "id", "name");
                    }
                    // add the specified label if it doesn't exist
                    if (!isset($labels[$name]) && $op === "a") {
                        $labels[$name] = Arsse::$db->labelAdd(Arsse::$user->id, ['name' => $name]);
                    }
                    Arsse::$db->labelArticlesSet(Arsse::$user->id, $m[1], $c, $op === "a" ? Database::ASSOC_ADD : Database::ASSOC_REMOVE, true);
                } elseif (preg_match(self::STATE_PATTERN, $s, $m)) {
                    $state = $m[1];
                    if ($state === "com.google/read") {
                        Arsse::$db->articleMark(Arsse::$user->id, ['read' => $op === "a" ? true : false], $c);
                    } elseif ($state === "com.google/kept-unread" || $state === "com.google/unread") {
                        Arsse::$db->articleMark(Arsse::$user->id, ['read' => $op === "a" ? false : true], $c);
                    } elseif ($state === "com.google/starred") {
                        Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $op === "a" ? true : false], $c);
                    } elseif (in_array($state, self::KNOWN_STATES)) {
                        // other known states are a no-op
                        continue;
                    } else {
                        return self::respError(["InvalidStream", $s]);
                    }
                } else {
                    return self::respError(["InvalidStream", $s]);
                }
            }
        }
        $tr->commit();
        return HTTP::respText("OK");
    }

    protected function streamMark(string $target, array $query, array $body, string $format): ResponseInterface {
        if (!isset($body['s'])) {
            return self::respError(["ParameterRequired", "s"]);
        }
        if ($c = $this->streamContext($body['s'])) {
            if (isset($body['ts'])) {
                // the timestamp must be at least seven digits (the last six digits are discarded)
                preg_match('/^(\d+)\d{6}$/', $body['ts'], $m);
                $c->modifiedRange(null, (int) ($m[1] ?? 0));
            }
            try {
                Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
            } catch (ExceptionInput $e) {
                return self::respError($e);
            }
        }
        return HTTP::respText("OK");
    }

    /** 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#subscription-list
     * @see https://www.inoreader.com/developers/subscription-list
     */
    protected function subscriptionList(string $target, array $query, array $body, string $format): ResponseInterface {
        $out = [];
        $sort = 0;
        // get the tag list of each subscription
        $tags = [];
        foreach (Arsse::$db->tagSummarize(Arsse::$user->id) as $t) {
            if (!isset($tags[$t['subscription']])) {
                $tags[$t['subscription']] = [];
            }
            $tags[$t['subscription']][] = $t['name'];
        }
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $f) {
            // NOTE: FreshRSS omits firstitemmsec, and FeedHQ seems to populate it with nonsense, so we feel confident in omitting it
            $out[] = [
                'id' => "feed/{$f['id']}",
                'title' => $f['title'],
                'categories' => array_map(function($t) {
                    return [
                        'id' => "user/-/label/$t",
                        'label' => $t,
                    ];
                }, $tags[$f['id']] ?? []),
                'url' => $f['url'], // NOTE: This appears to be a FreshRSS extension and is expected by Newsflash
                'htmlUrl' => $f['source'],
                'iconUrl' => $f['icon_url'] ?? $this->origin()."freshrss/default.png", // this appears to be a common extension; the fallback value points to an image approximating FreshRSS' generic icon
                'frss:priority' => "main",
                'sortid' => $this->makeSortId(++$sort),
            ];
        }
        return $this->respond($format, ['subscriptions' => $out]);
    }
    
    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#subscribed */
    protected function subscriptionValid(string $target, array $query, array $body, string $format): ResponseInterface {
        if (!isset($query['s'])) {
            return self::respError(["ParameterRequired", "s"]);
        }
        try {
            if (preg_match(self::FEED_PATTERN, $query['s'], $m)) {
                Arsse::$db->subscriptionLookup(Arsse::$user->id, $m[1]);
            } elseif (preg_match(self::SUBSCRIPTION_PATTERN, $query['s'], $m)) {
                Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, (int) $m[1]);
            } else {
                return self::respError(["InvalidStream", $query['s']]);
            }
            return HTTP::respText("true");
        } catch (ExceptionInput $e) {
            return HTTP::respText("false");
        }
    }

    /** @see https://github.com/theoldreader/api?tab=readme-ov-file#adding-subscription */
    protected function subscriptionAdd(string $target, array $query, array $body, string $format): ResponseInterface {
        if (!isset($body['quickadd'])) {
            return self::respError(["ParameterRequired", "quickadd"]);
        } elseif (preg_match(self::FEED_PATTERN, $body['quickadd'], $m)) {
            $url = $m[1];
        } else {
            $url = $body['quickadd'];
        }
        try {
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, $url, true);
            // get the effective feed URL in case of redirects, as well as the title
            $data = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $id);
        } catch (FeedException|ExceptionInput $e) {
            if ($e->getSymbol() === "constraintViolation") {
                $message = Arsse::$lang->msg("API.Reader.Error.DuplicateSubscription", ['url' => $url]);
            } else {
                $message = $e->getMessage();
            }
            // NOTE: This is how at least FreshRSS and The Old Reader respond in error cases
            return $this->respond($format, [
                'numResults' => 0,
                'query' => $url,
                'error' => $message,
            ], 400);
        }
        return $this->respond($format, [
            'numResults' => 1,
            'query'      => $data['url'],
            'streamId'   => "feed/".$data['id'],
            'streamName' => (string) $data['title'], // This is apparently a FreshRSS extension
        ]);
    }

    
    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#subscription-edit */
    protected function subscriptionEdit(string $target, array $query, array $body, string $format): ResponseInterface {
        // check required parameters
        if (!isset($body['ac'])) {
            return self::respError(["ParameterRequired", "ac"]);
        } elseif (!in_array($body['ac'], ["subscribe", "unsubscribe", "edit"])) {
            return self::respError(["InvalidValue", "ac", $body['ac']]);
        } elseif (!isset($body['s'])) {
            return self::respError(["ParameterRequired", "s"]);
        }
        $checkLabels = function(array $labels, string $field): array {
            $out = [];
            foreach ($labels as $l) {
                if (preg_match(self::LABEL_PATTERN, $l, $m)) {
                    $out[] = $m[1];
                } else {
                    throw new Exception("InvalidValue", [$field, $l]);
                }
            }
            return $out;
        };
        // perform whichever operation is requested
        if ($body['ac'] === "subscribe") {
            if (!isset($body['t'])) { // subscription title
                return self::respError(["ParameterRequired", "t"]);
            }
            if (preg_match(self::FEED_PATTERN, $body['s'], $m)) {
                $url = $m[1];
            } else {
                return self::respError(["InvalidValue", "s", $body['s']]);
            }
            if ($body['a']) {
                $body['a'] = $checkLabels($body['a'], "a");
            }
            try {
                $id = Arsse::$db->subscriptionReserve(Arsse::$user->id, $url, true);
            } catch (ExceptionInput) {
                return self::respError(["DuplicateSubscription", 'url' => $url]);
            }
            // start a transaction for the rest of the process so any errors simply roll back everything
            $tr = Arsse::$db->begin();
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['title' => $body['t']], true);
            if ($body['a']) {
                // if we're to add tags to the subscription, ensure the tags
                //   exist first; Reader doesn't treat tags/labels as distinct
                //   objects like we do, so we must transparently manage the
                //   objects behind the scenes
                $existing = array_column(iterator_to_array(Arsse::$db->tagList(Arsse::$user->id, true)), "id", "name");
                foreach ($body['a'] as $name) {
                    if (!isset($existing[$name])) {
                        $existing[$name] = Arsse::$db->tagAdd(Arsse::$user->id, ['name' => $name]);
                    }
                    Arsse::$db->tagSubscriptionsSet(Arsse::$user->id, $existing[$name], [$id]);
                }
            }
            Arsse::$db->subscriptionReveal(Arsse::$user->id, $id);
        } elseif ($body['ac'] === "unsubscribe") {
            $tr = Arsse::$db->begin();
            if (preg_match(self::FEED_PATTERN, $body['s'], $m)) {
                $url = $m[1];
                $id = Arsse::$db->subscriptionLookup(Arsse::$user->id, $url);
            } elseif (preg_match(self::SUBSCRIPTION_PATTERN, $body['s'], $m)) {
                $id = (int) $m[1];
            } else {
                return self::respError(["InvalidValue", "s", $body['s']]);
            }
            Arsse::$db->subscriptionRemove(Arsse::$user->id, $id);
        } elseif ($body['ac'] === "edit") {
            $tr = Arsse::$db->begin();
            if (preg_match(self::FEED_PATTERN, $body['s'], $m)) {
                $url = $m[1];
                $id = Arsse::$db->subscriptionLookup(Arsse::$user->id, $url);
            } elseif (preg_match(self::SUBSCRIPTION_PATTERN, $body['s'], $m)) {
                $id = (int) $m[1];
            } else {
                return self::respError(["InvalidValue", "s", $body['s']]);
            }
            if (isset($body['t'])) {
                Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['title' => $body['t']]);
            }
            if ($body['a']) {
                $existing = array_column(iterator_to_array(Arsse::$db->tagList(Arsse::$user->id, true)), "id", "name");
                $body['a'] = $checkLabels($body['a'], "a");
                foreach ($body['a'] as $name) {
                    if (!isset($existing[$name])) {
                        $existing[$name] = Arsse::$db->tagAdd(Arsse::$user->id, ['name' => $name]);
                    }
                    Arsse::$db->tagSubscriptionsSet(Arsse::$user->id, $existing[$name], [$id]);
                }
            }
            $body['r'] = $checkLabels($body['r'], "r");
            foreach ($body['r'] as $name) {
                try {
                    Arsse::$db->tagSubscriptionsSet(Arsse::$user->id, $name, [$id], Database::ASSOC_REMOVE, true);
                } catch (ExceptionInput $e) {
                    // ignore errors
                }
            }
        }
        $tr->commit();
        return HTTP::respText("OK");
    }

    protected function subscriptionImport(string $target, array $query, string $body, string $format): ResponseInterface {
        // NOTE: FeedHQ would return the number of imported feeds along with
        //   the success signal (e.g. "OK: 12"), but FreshRSS does not, and
        //   some FreshRSS clients rely more on the "OK" text than the 200
        //   success code for detecting success, so we omit the difference in
        //   case it causes problems.
        try {
            Arsse::$obj->get(OPML::class)->import(Arsse::$user->id, $body);
        } catch (AbstractException $e) {
            return self::respError($e);
        }
        return HTTP::respText("OK");
    }

    protected function subscriptionExport(string $target, array $query, array $body, string $format): ResponseInterface {
        // NOTE: Our OPML import/export functionality is the same regardless of
        //   exposed protocol. Feeds will be nested into folders as defined in
        //   other protocols, and labels set in this protocols will be exported
        //   as tags, with commas silently dropped due to OPML limitations
        return HTTP::respText(Arsse::$obj->get(OPML::class)->export(Arsse::$user->id), 200, ['Content-Type' => "application/xml"]);
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
                'id'                      => "feed/".$sub['id'],
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
            $tags[$tag['name']]['ts'] = max($tags[$tag['name']]['ts'], $summary[$tag['subscription']]['ts']);
        }
        // add tags to output
        foreach ($tags as $name => $data) {
            $out[] = [
                'id'                      => "user/{$meta['num']}/label/$name",
                'count'                   => $data['count'],
                'newestItemTimestampUsec' => Date::transform($data['ts'], "unix", "sql")."000000",
            ];
        }
        // add labels to output
        foreach (Arsse::$db->labelList(Arsse::$user->id) as $label) {
            $out[] = [
                'id'                      => "user/{$meta['num']}/label/{$label['name']}",
                'count'                   => (int) $label['articles'] - (int) $label['read'],
                'newestItemTimestampUsec' => $label['article_modified'] ? Date::transform($label['article_modified'], "unix", "sql")."000000" : null,
            ];
        }
        // add "reading list" (all articles) to output
        $out[] = [
            'id' => "user/{$meta['num']}/state/com.google/reading-list",
            'count' => $total,
            'newestItemTimestampUsec' => Date::transform($ts, "unix", "sql")."000000",
        ];
        $out = [
            'max' => $total,
            'unreadcounts' => $out,
        ];
        // return the whole list
        return self::respond($format, $out);
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-items-count */
    protected function itemCount(string $target, array $query, array $body, string $format): ResponseInterface {
        $out = 0;
        if (!isset($query['s'])) {
            return self::respError(["ParameterRequired", "s"]);
        }
        // convert the stream ID to a context
        if ($c = $this->streamContext($query['s'])) {
            $tr = Arsse::$db->begin();
            // get the count of articles matched by the context
            $out += Arsse::$db->articleCount(Arsse::$user->id, $c);
            // if the most recent date is requested as well, jump through some hoops to get it
            if ($out && $query['a']) {
                // this is quite inefficient, but it's a non-default option of a function FreshRSS doesn't even implement, so there's little reason to optimize for it
                // NOTE: FeedHQ appears to have a bug whereby a count of zero will still blindly get the date from index 0. We simply skip the whole thing instead
                $c = (clone $c)->limit(1);
                $date = Arsse::$db->articleList(Arsse::$user->id, $c, ["modified_date"], ["modified_date desc"])->getValue();
                $out = $out."#".Date::transform($date, "F d, Y", "sql"); // FeedHQ's Python format string is "%B %d, %Y"
            }
        }
        return HTTP::respText("$out");
    }

    /** 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-items-ids
     * @see https://github.com/bazqux/bazqux-api?tab=readme-ov-file#item-ids
     * @see https://github.com/mihaip/google-reader-api/blob/master/wiki/ApiStreamItemsIds.wiki */
    protected function itemIds(string $target, array $query, array $body, string $format): ResponseInterface {
        $out = [];
        $latest = 0;
        if ($context = $this->articleContext($query)) {
            $asc = $query['r'] === "o";
            $sort = $asc ? ["edition"] : ["edition desc"];
            $tr = Arsse::$db->begin();
            foreach (Arsse::$db->articleList(Arsse::$user->id, $context, ["id", 'edition', "modified_date"], $sort) as $i) {
                // prepare the entry
                $out[] = [
                    'id' => (string) $i['id'], // FreshRSS returns the ID as a string, so we do the same
                    'timestampUsec' => Date::transform($i['modified_date'], "unix", "sql")."000000",
                ];
                $latest = $latest ? ($asc ? max($latest, (int) $i['edition']) : min($latest, (int) $i['edition'])) : $i['edition'];
            }
        }
        $out = ['itemRefs' => $out];
        if (sizeof($out['itemRefs']) === $this->pageSize($query['n'])) {
            // there are probably more items, so we construct a continuation string
            $out['continuation'] = $this->computeContinuation($query, $latest);
        }
        return self::respond($format, $out);
    }

    /** 
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-items-contents
     * @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-contents */
    protected function itemContents(string $target, array $query, array $body, string $format): ResponseInterface {
        // determine the list of articles
        if ($body['i'] ?? []) {
            $articles = array_map([$this, "itemIdDecode"], $body['i']);
        } elseif ($query['i'] ?? []) {
            $articles = array_map([$this, "itemIdDecode"], $query['i']);
        } else {
            return self::respError(["ParameterRequired", "i"]);
        }
        // fetch the articles
        $context = (new Context)->articles($articles);
        return self::respond($format, $this->articleFetch($context, $query, false));
    }

    /** @see https://feedhq.readthedocs.io/en/latest/api/reference.html#stream-contents */
    protected function streamContents(string $target, array $query, array $body, string $format): ResponseInterface {
        // look for a stream ID in the URL
        $stream = substr($target, strlen("/stream/contents/"));
        if (strlen($stream)) {
            // if there is a stream ID in the URL, stuff its decoded version into the query
            $query['s'] = rawurldecode($stream);
        }
        // fetch the articles
        $context = $this->articleContext($query);
        return self::respond($format, $this->articleFetch($context, $query, true));
    }

    protected function articleFetch(?Context $context, array $query, bool $allowContinuation): array {
        $asc = $query['r'] === "o";
        $sort = $asc ? ["edition"] : ["edition desc"];
        $latest = 0;
        $out = [];
        if ($context) {
            $tr = Arsse::$db->begin();
            $tags = [];
            foreach (Arsse::$db->tagSummarize(Arsse::$user->id) as $assoc) {
                if (!isset($tags[$assoc['subscription']])) {
                    $tags[$assoc['subscription']] = [];
                }
                $tags[$assoc['subscription']][] = $assoc['name'];
            }
            // loop through the articles
            foreach (Arsse::$db->articleList(Arsse::$user->id, $context, [
                "id",
                'edition',
                "modified_date",
                "published_date",
                "edited_date",
                "subscription",
                "subscription_url",
                "subscription_title",
                "unread",
                "starred",
                "author",
                "title",
                "url",
                "content",
                "media_url",
                "media_type",
            ], $sort) as $i) {
                // prepare the entry
                $out[] = [
                    'id'            => $this->itemIdEncode((int) $i['id']),
                    'crawlTimeMsec' => Date::transform($i['modified_date'], "unix", "sql")."000",
                    'timestampUsec' => Date::transform($i['modified_date'], "unix", "sql")."000000",
                    'published'     => Date::transform($i['published_date'], "unix", "sql"),
                    'updated'       => Date::transform($i['edited_date'], "unix", "sql"), // FreshRSS does not include this for whatever reason
                    'title'         => $i['title'],
                    'canonical'     => [['href' => $i['url']]],
                    'alternate'     => [['href' => $i['url'], 'type' => "text/html"]],
                    'categories'    => $this->itemCategories($i, $tags),
                    'origin'        => [
                        'streamId' => "feed/".$i['subscription'],
                        'htmlUrl'  => $i['subscription_url'],
                        'title'    => $i['subscription_title'],
                    ],
                    'summary'       => [
                        //'direction' => "ltr", // FIXME: a future feed parser should be able to expose this information; FreshRSS does not include it
                        'content'   => $i['content'],
                    ],
                    'enclosure'    => isset($i['media_url']) ? [['href' => $i['media_url'], 'type' => $i['media_type']]] : [], // enclosures appear to be a FreshRSS extension 
                    'author'        => $i['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ];
                // note the smallest/largest editon ID (depending on sort direction) for continuation computation
                $latest = $latest ? ($asc ? max($latest, (int) $i['edition']) : min($latest, (int) $i['edition'])) : $i['edition'];
            }
        }
        $out = [
            'id'      => "user/-/state/com.google/reading-list", // NOTE: FreshRSS uses the reading list stream ID for any stream; this avoids a bunch of pointless complexity, so we do the same
            'updated' => Date::transform($this->now(), "unix"),
            'items'   => $out
        ];
        if ($allowContinuation && sizeof($out['items']) === $this->pageSize($query['n'])) {
            // there are probably more items, so we construct a continuation string
            $out['continuation'] = $this->computeContinuation($query, $latest);
        }
        return $out;
    }

    protected function itemCategories(array $item, array $tags): array {
        assert(isset($item['id'], $item['subscription'], $item['unread'], $item['starred']), new \Exception("Supplied article is missing a required column"));
        $out = [
            "user/-/state/com.google/reading-list",
            "user/-/state/org.freshrss/main",
        ];
        if ($item['unread']) {
            // NOTE: Most Reader implementations don't seem to have an
            //   opposite-of-read state, but both FreshRSS and FeedHQ do.
            //   Unfortunately they are different from each other. We therefore
            //   expose both.
            $out[] = "user/-/state/com.google/unread";      // FreshRSS
            $out[] = "user/-/state/com.google/kept-unread"; // FeedHQ
        } else {
            $out[] = "user/-/state/com.google/read";
        }
        if ($item['starred']) {
            $out[] = "user/-/state/com.google/starred";
        }
        $out = array_merge($out, array_map(function($v) {
            return "user/-/label/$v";
        }, array_unique(array_merge($tags[$item['subscription']] ?? [], Arsse::$db->articleLabelsGet(Arsse::$user->id, $item['id'], true)))));
        // add any author-supplied categories; this is a FreshRSS oddity
        $out = array_merge($out, Arsse::$db->articleCategoriesGet(Arsse::$user->id, $item['id']));
        return $out;
    }

    protected function articleContext(array &$query): ?Context {
        // parse the continuation string, if any
        if ($query['c']) {
            if (!($ct = @base64_decode($query['c'], true))) {
                throw new Exception("InvalidContinuation");
            }
            // replace the query data with the continuation data; a user
            //   might modify parts of the query to be in conflict with the
            //   continuation, so we simply take whatever is inside the
            //   continuation as authoritative; this ensures that constructing
            //   a new string for the next page later is accurate
            $query = $this->parseQuery($ct, self::CONTINUATION_PARAMS, false, false);
        }
        // set the sorting direction
        $asc = $query['r'] === "o";
        // NOTE: streams can be refined by adding an AND condition with 'it'
        //   and/or an AND NOT condition with 'xt'
        if ($c = $this->streamContext($query['s'] ?? "", $query['it'], $query['xt'])) {
            // fairly typical time-based constraits can also be applied
            $c->modifiedRange($query['ot'], $query['nt']);
            // the 'i' parameter is only valid in continuations and is our page anchor
            if (isset($query['i'])) {
                $c->editionRange($asc ? $query['i'] : null, $asc ? null : $query['i']);
            }
            // pagination is always applied
            $c->limit($this->pageSize($query['n']));
        }
        // return the context
        return $c;
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
            $anchor++;
        } else {
            $anchor--;
        }
        // sort by key for consistency
        ksort($query);
        // add our anchor
        $query['i'] = $anchor;
        // turn the array back into a url-encoded string and return it base64
        $out = [];
        foreach ($query as $k => $v) {
            if ($v === null) {
                // nulls are unnecessary
                continue;
            } elseif ($v instanceof \DateTimeInterface) {
                // dates must be converted back into integers
                $v = Date::transform($v, "unix");
            }
            $v = urlencode((string) $v);
            $out[] = "$k=$v";
        }
        return base64_encode(implode("&", $out));
    }

    protected static function respond(string $format, array $data, int $status = 200, array $headers = []): ResponseInterface {
        assert(in_array($format, ["json", "xml", "atom"]), new \Exception("Invalid format passed for output"));
        if ($format === "xml") {
            $d = new \DOMDocument("1.0", "utf-8");
            $d->appendChild(self::makeXML($data, $d));
            return HTTP::respXml($d->saveXML($d->documentElement, \LIBXML_NOEMPTYTAG));
        } elseif ($format === "atom") {
            return self::respError("AtomNotImplemented");
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
        $object = is_object($data) || ($data && !isset($data[0]));
        $p = $d->createElement($object ? "object" : "list");
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $pp = $d->createElement("string", $v);
            } elseif (is_numeric($v)) {
                $pp = $d->createElement("number", (string) $v);
            } elseif (is_array($v) || is_object($v)) {
                $pp = self::makeXML($v, $d);
            } elseif ($v === null) {
                // FeedHQ doesn't seem to handle nulls, so this may be wrong
                $pp = $d->createElement("null");
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
