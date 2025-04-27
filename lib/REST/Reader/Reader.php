<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\Context\RootContext;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Exception;
use MensBeam\Mime\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Reader extends \JKingWeb\Arsse\REST\AbstractHandler {
    use Common;

    protected const BODY_IGNORE = 0;
    protected const BODY_READ = 1;
    protected const BODY_PARSE= 2;
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
        '/token'                  => ["tokenGet",           true,  false, false, false, []],
        '/unread-count'           => ["countsGet",          true,  false, false, false, []],
        '/user-info'              => ["userGet",            true,  false, false, false, []],
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
        // parse body and query arguments (the body is not parsed for OPML import, only extracted)
        $bodyMode = $method === "POST" ? ($func !== "subscriptionImport" ? self::BODY_PARSE : self::BODY_READ) : self::BODY_IGNORE;
        [$format, $query, $body] = $this->inputParse($req, $params, $bodyMode);
        // perform content negotiation if a format is not specified in the query
        $format = $format ?? self::FORMAT_MAP[MimeType::negotiate(self::OUTPUT_TYPES, $req->getHeaderLine("Accept")) ?? "application/xml"];
        $format = ($format === "atom" && !$atomAllowed) ? "xml" : $format;
        // handle the request
        try {
            return $this->$func($target, $query, $body, $format);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            // if there was a REST exception return 400
            return self::respError($e, 400);
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
    protected function inputParse(ServerRequestInterface $req, array $allowed, int $bodyMode): array {
        $format = null;
        // fill an array with all allowed keys
        foreach ($allowed as $k => $t) {
            $outG[$k] = ($t >= V::M_ARRAY) ? [] : null;
        }
        // parse the query
        foreach (explode("&", parse_url($req->getRequestTarget(), \PHP_URL_QUERY) ?? "") as $q) {
            [$k, $v] = array_pad(explode("=", $q, 2), 2, "");
            $v = urldecode($v);
            if ($k === "output" && in_array($v, self::FORMAT_MAP)) {
                // handle the "output" parameter which may dictate the format of our output
                $format = $v;
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
                $outG[$k][] = V::normalize($v, $t + V::M_DROP, "unix");
            } else {
                // NOTE: The last value is kept in case of duplicates; this is
                //   what FreshRSS does because it's what PHP does
                $outG[$k] = V::normalize($v, $t + V::M_DROP, "unix");
            }
        }
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
                // otherwise parse it similar to the query
                foreach ($allowed as $k => $t) {
                    $outP[$k] = ($t >= V::M_ARRAY) ? [] : null;
                }
                $outP['T'] = null; // POST token
                foreach (explode("&", $body) as $q) {
                    [$k, $v] = array_pad(explode("=", $q, 2), 2, "");
                    $v = urldecode($v);
                    if ($k === "T") {
                        // handle POST tokens
                        $outP[$k] = $v;
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
                        $outG[$k][] = V::normalize($v, $t + V::M_DROP, "unix");
                    } else {
                        // NOTE: The last value is kept in case of duplicates; this is
                        //   what FreshRSS does because it's what PHP does
                        $outG[$k] = V::normalize($v, $t + V::M_DROP, "unix");
                    }
                }
            }
        }
        return [$format, $outG, $outP];
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

    /** Converts a stream identifier into a database context
     * 
     * Because feed streams are identified by URL this procedure my require
     * database activity, but should nevertheless be fast and safe
     * 
     * A null return value indicates a stream which will always return no articles
     * 
     * @return Context|UnionContext|null
     */
    protected function streamContext(?string $stream): ?RootContext {
        $stream = $stream ?? "";
        // most contexts will not be union contexts, so we'll assume the common case
        $c = new Context;
        if ($stream === "") {
            // NOTE: BazQux and FreshRSS both interpret absence of a stream as
            //   the "reading list" stream (all articles) in at least some
            //   circumstances. We apply this interpretation universally and
            //   leave it to the individual functions to determine whether the
            //   reading list is a valid target
            return $c;
        } elseif (preg_match('<^user/[^/]+/label/(.+)>', $stream, $m)) {
            return $c->tagName($m[1]);
        } elseif (preg_match('<^user/[^/]+/state/com.google/(.+)>', $stream, $m)) {
            switch ($m[1]) {
                case "read":
                    return $c->unread(false);
                case "kept-unread":
                    return $c->unread(true);
                case "broadcast":
                case "broadcast-fiends":
                    return null;
                case "reading-list":
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
            // this requires a union context
            $u = new UnionContext();
            foreach (explode("|", $stream) as $s) {
                $cc = $this->streamContext($s);
                if (!$cc) {
                    // invalid context, which invalidates the entire splice
                    return null;
                } elseif ($cc == $c) {
                    // the reading list is all articles; we don't need a union
                    //   context if we're just selecting everything anyway
                    return $c;
                }
                $u[] = $cc;
            }
            return $u;
        }
        throw new \Exception("TODO: Turn this into some proper exception");
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