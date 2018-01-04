<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

class REST {
    protected $apis = [
        // NextCloud News version enumerator
        'ncn' => [
            'match' => '/index.php/apps/news/api',
            'strip' => '/index.php/apps/news/api',
            'class' => REST\NextCloudNews\Versions::class,
        ],
        // NextCloud News v1-2  https://github.com/nextcloud/news/blob/master/docs/externalapi/Legacy.md
        'ncn_v1-2' => [
            'match' => '/index.php/apps/news/api/v1-2/',
            'strip' => '/index.php/apps/news/api/v1-2',
            'class' => REST\NextCloudNews\V1_2::class,
        ],
        'ttrss_api' => [ // Tiny Tiny RSS  https://git.tt-rss.org/git/tt-rss/wiki/ApiReference
            'match' => '/tt-rss/api/',
            'strip' => '/tt-rss/api',
            'class' => REST\TinyTinyRSS\API::class,
        ],
        'ttrss_icon' => [ // Tiny Tiny RSS feed icons
            'match' => '/tt-rss/feed-icons/',
            'strip' => '/tt-rss/feed-icons/',
            'class' => REST\TinyTinyRSS\Icon::class,
        ],
        // Other candidates:
        // Google Reader        http://feedhq.readthedocs.io/en/latest/api/index.html
        // Fever                https://feedafever.com/api
        // Feedbin v2           https://github.com/feedbin/feedbin-api
        // Feedbin v1           https://github.com/feedbin/feedbin-api/commit/86da10aac5f1a57531a6e17b08744e5f9e7db8a9
        // Miniflux             https://github.com/miniflux/miniflux/blob/master/docs/json-rpc-api.markdown
        // CommaFeed            https://www.commafeed.com/api/
        // NextCloud News v2    https://github.com/nextcloud/news/blob/master/docs/externalapi/External-Api.md
        // Selfoss              https://github.com/SSilence/selfoss/wiki/Restful-API-for-Apps-or-any-other-external-access
        // BirdReader           https://github.com/glynnbird/birdreader/blob/master/API.md
        // Proprietary (centralized) entities:
        // NewsBlur             http://www.newsblur.com/api
        // Feedly               https://developer.feedly.com/
    ];

    public function __construct() {
    }

    public function dispatch(REST\Request $req = null): \Psr\Http\Message\ResponseInterface {
        if ($req===null) {
            $req = new REST\Request();
        }
        $api = $this->apiMatch($req->url, $this->apis);
        $req->url = substr($req->url, strlen($this->apis[$api]['strip']));
        $req->refreshURL();
        $class = $this->apis[$api]['class'];
        $drv = new $class();
        if ($req->head) {
            $res =  $drv->dispatch($req);
            $res->head = true;
            return $res;
        } else {
            return $drv->dispatch($req);
        }
    }

    public function apiMatch(string $url, array $map): string {
        // sort the API list so the longest URL prefixes come first
        uasort($map, function ($a, $b) {
            return (strlen($a['match']) <=> strlen($b['match'])) * -1;
        });
        // find a match
        foreach ($map as $id => $api) {
            if (strpos($url, $api['match'])===0) {
                return $id;
            }
        }
        // or throw an exception otherwise
        throw new REST\Exception501();
    }
}
