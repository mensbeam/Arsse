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
use JKingWeb\Arsse\Misc\ValueInfo;
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

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        $inR = $req->getQueryParams() ?? [];
        $inW = $req->getParsedBody() ?? [];
        if (!array_key_exists("api", $inR)) {
            // the original would have shown the Fever UI in the absence of the "api" parameter, but we'll return 404
            return new EmptyResponse(404);
        }
        $xml = $inR['api'] === "xml";
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
                if ($this->logIn(strtolower($inW['api_key'] ?? ""))) {
                    $out = $this->processRequest($this->baseResponse(true), $inR, $inW);
                } else {
                    $out = $this->baseResponse(false);
                }
                // return the result, possibly formatted as XML
                return $this->formatResponse($out, $xml);
                break;
            default:
                return new EmptyResponse(405, ['Allow' => "OPTIONS,POST"]);
        }
    }

    protected function processRequest(array $out, array $G, array $P): array {
        if (array_key_exists("feeds", $G) || array_key_exists("groups", $G)) {
            if (array_key_exists("groups", $G)) {
                $out['groups'] = $this->getGroups();
            }
            if (array_key_exists("feeds", $G)) {
                $out['feeds'] = $this->getFeeds();
            }
            $out['feeds_groups'] = $this->getRelationships();
        }
        if (array_key_exists("favicons", $G)) {
            # deal with favicons
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
}
