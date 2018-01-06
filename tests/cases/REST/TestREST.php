<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST;

use JKingWeb\Arsse\REST;
use JKingWeb\Arsse\REST\Exception501;

/** @covers \JKingWeb\Arsse\REST */
class TestREST extends \JKingWeb\Arsse\Test\AbstractTest {

    /** @dataProvider provideApiMatchData */
    public function testMatchAUrlToAnApi($apiList, string $input, array $exp) {
        $r = new REST($apiList);
        try {
            $out = $r->apiMatch($input);
        } catch (Exception501 $e) {
            $out = [];
        }
        $this->assertEquals($exp, $out);
    }

    public function provideApiMatchData() {
        $real = null;
        $fake = [
            'unstripped' => ['match' => "/full/url", 'strip' => "", 'class' => "UnstrippedProtocol"],
        ];
        return [
            [$real, "/index.php/apps/news/api/v1-2/feeds", ["ncn_v1-2",    "/feeds",     \JKingWeb\Arsse\REST\NextCloudNews\V1_2::class]],
            [$real, "/index.php/apps/news/api/v1-2",       ["ncn",         "/v1-2",      \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
            [$real, "/index.php/apps/news/api/",           ["ncn",         "/",          \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
            [$real, "/index%2Ephp/apps/news/api/",         ["ncn",         "/",          \JKingWeb\Arsse\REST\NextCloudNews\Versions::class]],
            [$real, "/index.php/apps/news/",               []],
            [$real, "/index!php/apps/news/api/",           []],
            [$real, "/tt-rss/api/index.php",               ["ttrss_api",   "/index.php", \JKingWeb\Arsse\REST\TinyTinyRSS\API::class]],
            [$real, "/tt-rss/api",                         ["ttrss_api",   "",           \JKingWeb\Arsse\REST\TinyTinyRSS\API::class]],
            [$real, "/tt-rss/API",                         []],
            [$real, "/tt-rss/api-bogus",                   []],
            [$real, "/tt-rss/api bogus",                   []],
            [$real, "/tt-rss/feed-icons/",                 ["ttrss_icon",  "",           \JKingWeb\Arsse\REST\TinyTinyRSS\Icon::class]],
            [$real, "/tt-rss/feed-icons/",                 ["ttrss_icon",  "",           \JKingWeb\Arsse\REST\TinyTinyRSS\Icon::class]],
            [$real, "/tt-rss/feed-icons",                  []],
            [$fake, "/full/url/",                          ["unstripped",  "/full/url/", "UnstrippedProtocol"]],
            [$fake, "/full/url-not",                       []],
        ];
    }
}