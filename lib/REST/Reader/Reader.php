<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Exception;
use MensBeam\Mime\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Reader extends \JKingWeb\Arsse\REST\AbstractHandler {
    use Common;

    /** The list of URL matches for calls
     * 
     * An asterisk in a URL is a stand-in for any stream ID
    */
    protected const CALLS = [         // Handler method     GET    POST   Allowed params
        '/disable-tag'            => ["tagDisable",         false, true,  ['s' => V::T_STRING, 't' => V::T_STRING]],
        '/edit-tag'               => ["tagEdit",            false, true,  ['i' => V::T_MIXED | V::T_ARRAY, 'a' => V::T_STRING | V::T_ARRAY, 'r' => V::T_STRING | V::T_ARRAY]],
        '/friend/list'            => ["friendsGet",         true,  false, []],
        '/mark-all-as-read'       => ["streamMark",         false, true,  ['s' => V::T_STRING, 'ts' => V::T_STRING]], // 'ts' is actually a date, but it's in an irregular format, so will require special handling
        '/preference/list'        => ["prefsGet",           true,  false, []],
        '/preference/stream/list' => ["prefsStreamGet",     true,  false, []],
        '/rename-tag'             => ["tagRename",          false, true,  ['s' => V::T_STRING, 't' => V::T_STRING, 'dest' =>V::T_STRING]],
        '/stream/contents/*'      => ["streamContents",     true,  false, ['r' => V::T_STRING, 'n' => V::T_INT, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/stream/items/contents'  => ["itemContents",       true,  true,  []],
        '/stream/items/count'     => ["itemCount",          true,  false, ['s' => V::T_STRING, 'a' => V::T_BOOL]],
        '/stream/items/ids'       => ["itemIds",            true,  false, ['s' => V::T_STRING, 'n' => V::T_INT, 'includeAllDirectStreamIds' => V::T_BOOL, 'c' => V::T_STRING, 'xt' => V::T_STRING, 'it' => V::T_STRING, 'ot' => V::T_DATE, 'nt' => V::T_DATE]],
        '/subscribed'             => ["subscriptionValid",  true,  false, ['s' => V::T_STRING]],
        '/subscription/edit'      => ["subscriptionEdit",   false, true,  ['ac' => V::T_STRING, 's' => V::T_STRING, 't' => V::T_STRING, 'a' => V::T_STRING | V::T_ARRAY, 'r' => V::T_STRING | V::T_ARRAY]],
        '/subscription/export'    => ["subscriptionExport", true,  false, []],
        '/subscription/import'    => ["subscriptionImport", false, true,  []],
        '/subscription/list'      => ["subscriptionList",   true,  false, []],
        '/subscription/quickadd'  => ["subscriptionAdd",    false, true,  ['quickadd' => V::T_STRING]],
        '/tag/list'               => ["tagList",            true,  false, []],
        '/token'                  => ["tokenGet",           true,  false, []],
        '/unread-count'           => ["countsGet",          true,  false, []],
        '/user-info'              => ["userGet",            true,  false, []],
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
        // perform content negotiation; this is used by some but not all routes
        $format = self::FORMAT_MAP[MimeType::negotiate(self::OUTPUT_TYPES, $req->getHeaderLine("Accept")) ?? "application/xml"];
        // determine which handler to call
        $func = $this->chooseCall($target, $method);
        if ($func instanceof ResponseInterface) {
            return $func;
        }
        [$func, $params] = $func;
        // parse body and query parameters
        if ($func === "subscriptionImport") {
            // OPML importing is a special case; our importing infrastructure will parse it
            $body = (string) $req->getBody();
        } else {
            $body = $this->argParse((string) $req->getBody(), $params);
        }
        $query = $this->argParse(parse_url($req->getRequestTarget(), \PHP_URL_QUERY) ?? "", $params);
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
            [$func, $GET, $POST, $params] = self::CALLS[$url];
            switch ($method) {
                case "GET":
                case "POST":
                    if ($$method) {
                        return [$func, $params];
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

    protected function argParse(string $data, array $allowed): array {
        // STUB
        return [];
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
    protected static function makeXML(array $data, \DOMDocument $d): \DOMElement {
        // this is a very simplistic check for an indexed array;
        //   it would not pass muster in the face of generic data,
        //   but we'll assume our code produces only well-ordered
        //   indexed arrays
        $object = !isset($v[0]);
        $p = $d->createElement($object ? "object" : "list");
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $pp = $d->createElement("string", $v);
            } elseif (is_numeric($v)) {
                $pp = $d->createElement("number", (string) $v);
            } elseif (is_array($v)) {
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