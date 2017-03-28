<?php
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
        // Other candidates:
        // NextCloud News v2    https://github.com/nextcloud/news/blob/master/docs/externalapi/External-Api.md
        // Feedbin v1           https://github.com/feedbin/feedbin-api/commit/86da10aac5f1a57531a6e17b08744e5f9e7db8a9
        // Feedbin v2           https://github.com/feedbin/feedbin-api
        // Tiny Tiny RSS        https://tt-rss.org/gitlab/fox/tt-rss/wikis/ApiReference
        // Fever                https://feedafever.com/api
        // NewsBlur             http://www.newsblur.com/api
    ];
    protected $data;
    
    function __construct() {
    }

    function dispatch(REST\Request $req = null): bool {
        if($req===null) $req = new REST\Request();
        $api = $this->apiMatch($url, $this->apis);
        $req->url = substr($url,strlen($this->apis[$api]['strip']));
        $class = $this->apis[$api]['class'];
        $drv = new $class();
        $drv->dispatch($req);
        return true;
    }

    function apiMatch(string $url, array $map): string {
        // sort the API list so the longest URL prefixes come first
        uasort($map, function($a, $b) {return (strlen($a['match']) <=> strlen($b['match'])) * -1;});
        // find a match
        foreach($map as $id => $api) {
            if(strpos($url, $api['match'])===0) return $id;
        }
        // or throw an exception otherwise
        throw new REST\ExceptionURL("apiNotSupported", $url);
    }
}