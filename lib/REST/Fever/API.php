<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\Target;
use JKingWeb\Arsse\REST\Exception404;
use JKingWeb\Arsse\REST\Exception405;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    const LEVEL = 3;

    // GET parameters for which we only check presence: these will be converted to booleans
    const PARAM_BOOL = ["groups", "feeds", "items", "favicons", "links", "unread_item_ids", "saved_item_ids"];
    // GET parameters which contain meaningful values
    const PARAM_GET = [
        'api'       => V::T_STRING, // this parameter requires special handling
        'page'      => V::T_INT, // parameter for hot links
        'range'     => V::T_INT, // parameter for hot links
        'offset'    => V::T_INT, // parameter for hot links
        'since_id'  => V::T_INT,
        'max_id'    => V::T_INT,
        'with_ids'  => V::T_STRING,
        'group_ids' => V::T_STRING, // undocumented parameter for 'items' lookup
        'feed_ids'  => V::T_STRING, // undocumented parameter for 'items' lookup
    ];
    // POST parameters, all of which contain meaningful values
    const PARAM_POST = [
        'api_key'              => V::T_STRING,
        'mark'                 => V::T_STRING,
        'as'                   => V::T_STRING,
        'id'                   => V::T_INT,
        'before'               => V::T_DATE,
        'unread_recently_read' => V::T_BOOL,
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $G = $this->normalizeInputGet($req->getQueryParams() ?? []);
        $P = $this->normalizeInputPost($req->getParsedBody() ?? []);
        if (!isset($G['api'])) {
            // the original would have shown the Fever UI in the absence of the "api" parameter, but we'll return 404
            return new EmptyResponse(404);
        }
        switch ($req->getMethod()) {
            case "OPTIONS":
                // do stuff
                break;
            case "POST":
                if (strlen($req->getHeaderLine("Content-Type")) && $req->getHeaderLine("Content-Type") !== "application/x-www-form-urlencoded") {
                    return new EmptyResponse(415, ['Accept' => "application/x-www-form-urlencoded"]);
                }
                $out = [
                    'api_version' => self::LEVEL,
                    'auth' => 0,
                ];
                if ($req->getAttribute("authenticated", false)) {
                    // if HTTP authentication was successfully used, set the expected user ID
                    Arsse::$user->id = $req->getAttribute("authenticatedUser");
                    $out['auth'] = 1;
                } elseif (Arsse::$conf->userHTTPAuthRequired || Arsse::$conf->userPreAuth || $req->getAttribute("authenticationFailed", false)) {
                    // otherwise if HTTP authentication failed or is required, deny access at the HTTP level
                    return new EmptyResponse(401);
                }
                // produce a full response if authenticated or a basic response otherwise
                if ($this->logIn(strtolower($P['api_key'] ?? ""))) {
                    $out = $this->processRequest($this->baseResponse(true), $G, $P);
                } else {
                    $out = $this->baseResponse(false);
                }
                // return the result, possibly formatted as XML
                return $this->formatResponse($out, ($G['api'] === "xml"));
            default:
                return new EmptyResponse(405, ['Allow' => "OPTIONS,POST"]);
        }
    }

    protected function normalizeInputGet(array $data): array {
        $out = [];
        if (array_key_exists("api", $data)) {
            // the "api" parameter must be handled specially as it a string, but null has special meaning
            $data['api'] = $data['api'] ?? "json";
        }
        foreach (self::PARAM_BOOL as $p) {
            // first handle all the boolean parameters
            $out[$p] = array_key_exists($p, $data);
        }
        foreach (self::PARAM_GET as $p => $t) {
            $out[$p] = V::normalize($data[$p] ?? null, $t | V::M_DROP, "unix");
        }
        return $out;
    }

    protected function normalizeInputPost(array $data): array {
        $out = [];
        foreach (self::PARAM_POST as $p => $t) {
            $out[$p] = V::normalize($data[$p] ?? null, $t | V::M_DROP, "unix");
        }
        return $out;
    }

    protected function processRequest(array $out, array $G, array $P): array {
        if ($G['feeds'] || $G['groups']) {
            if ($G['groups']) {
                $out['groups'] = $this->getGroups();
            }
            if ($G['feeds']) {
                $out['feeds'] = $this->getFeeds();
            }
            $out['feeds_groups'] = $this->getRelationships();
        }
        if ($G['favicons']) {
            # deal with favicons
        }
        if ($G['items']) {
            $out['items'] = $this->getItems($G);
            $out['total_items'] = Arsse::$db->articleCount(Arsse::$user->id);
        }
        if ($G['links']) {
            // TODO: implement hot links
            $out['links'] = [];
        }
        if ($G['unread_item_ids']) {
            $out['unread_item_ids'] = $this->getItemIds((new Context)->unread(true));
        }
        if ($G['saved_item_ids']) {
            $out['saved_item_ids'] = $this->getItemIds((new Context)->starred(true));
        }
        return $out;
    }

    protected function baseResponse(bool $authenticated): array {
        $out = [
            'api_version' => self::LEVEL,
            'auth' => (int) $authenticated,
        ];
        if ($authenticated) {
            // authenticated requests always include the most recent feed refresh
            $out['last_refreshed_on_time'] = $this->getRefreshTime();
        }
        return $out;
    }

    protected function formatResponse(array $data, bool $xml): ResponseInterface {
        if ($xml) {
            throw \Exception("Not implemented yet");
        } else {
            return new JsonResponse($data, 200, [], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        }
    }

    protected function logIn(string $hash): bool {
        // if HTTP authentication was successful and sessions are not enforced, proceed unconditionally
        if (isset(Arsse::$user->id) && !Arsse::$conf->userSessionEnforced) {
            return true;
        }
        try {
            // verify the supplied hash is valid
            $s = Arsse::$db->TokenLookup("fever.login", $hash);
        } catch (\JKingWeb\Arsse\Db\ExceptionInput $e) {
            return false;
        }
        // set the user name
        Arsse::$user->id = $s['user'];
        return true;
    }

    protected function getRefreshTime() {
        return Date::transform(Arsse::$db->subscriptionRefreshed(Arsse::$user->id), "unix");
    }

    protected function getFeeds(): array {
        $out = [];
        foreach (arsse::$db->subscriptionList(Arsse::$user->id) as $sub) {
            $out[] = [
                'id'                   => (int) $sub['id'],
                'favicon_id'           => (int) ($sub['favicon'] ? $sub['feed'] : 0),
                'title'                => (string) $sub['title'],
                'url'                  => $sub['url'],
                'site_url'             => $sub['source'],
                'is_spark'             => 0,
                'last_updated_on_time' => Date::transform($sub['edited'], "unix", "sql"),
            ];
        }
        return $out;
    }

    protected function getGroups(): array {
        $out = [];
        foreach (Arsse::$db->tagList(Arsse::$user->id) as $member) {
            $out[] = [
                'id' => (int) $member['id'],
                'title' => $member['name'],
            ];
        }
        return $out;
    }

    protected function getRelationships(): array {
        $out = [];
        $sets = [];
        foreach (Arsse::$db->tagSummarize(Arsse::$user->id) as $member) {
            if (!isset($sets[$member['id']])) {
                $sets[$member['id']] = [];
            }
            $sets[$member['id']][] = (int) $member['subscription'];
        }
        foreach ($sets as $id => $subs) {
            $out[] = [
                'group_id' => (int) $id,
                'feed_ids' => implode(",", $subs),
            ];
        }
        return $out;
    }

    protected function getItems(array $G): array {
        $c = (new Context)->limit(50);
        $reverse = false;
        // handle the standard options
        if ($G['with_ids']) {
            $c->articles(explode(",", $G['with_ids']));
        } elseif ($G['max_id']) {
            $c->latestArticle($G['max_id'] - 1);
            $reverse = true;
        } elseif ($G['since_id']) {
            $c->oldestArticle($G['since_id'] + 1);
        }
        // handle the undocumented options
        if ($G['group_ids']) {
            $c->tags(explode(",", $G['group_ids']));
        }
        if ($G['feed_ids']) {
            $c->subscriptions(explode(",", $G['feed_ids']));
        }
        // get results
        $out = [];
        $order = $reverse ? "id desc" : "id";
        foreach (Arsse::$db->articleList(Arsse::$user->id, $c, ["id", "subscription", "title", "author", "content", "url", "starred", "unread", "published_date"], [$order]) as $r) {
            $out[] = [
                'id'              => (int) $r['id'],
                'feed_id'         => (int) $r['subscription'],
                'title'           => (string) $r['title'],
                'author'          => (string) $r['author'],
                'html'            => (string) $r['content'],
                'url'             => (string) $r['url'],
                'is_saved'        => (int) $r['starred'],
                'is_read'         => (int) !$r['unread'],
                'created_on_time' => Date::transform($r['published_date'], "unix", "sql"),
            ];
        }
        return $out;
    }

    protected function getItemIds(Context $c = null): string {
        $out = [];
        foreach (Arsse::$db->articleList(Arsse::$user->id, $c) as $r) {
            $out[] = (int) $r['id'];
        }
        return implode(",", $out);
    }
}
