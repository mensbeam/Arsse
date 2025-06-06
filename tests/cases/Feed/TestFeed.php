<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Feed;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Test\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\JKingWeb\Arsse\Feed::class)]
#[Group('slow')]
class TestFeed extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $host = "http://localhost:8000/";
    protected $base = "";
    protected $latest = [
        [
            'id'                 => 1,
            'edited'             => '2000-01-01 00:00:00',
            'guid'               => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
            'url_title_hash'     => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6',
            'url_content_hash'   => 'fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4',
            'title_content_hash' => '18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
        ],
        [
            'id'                 => 2,
            'edited'             => '2000-01-02 00:00:00',
            'guid'               => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
            'url_title_hash'     => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153',
            'url_content_hash'   => '13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9',
            'title_content_hash' => '2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
        ],
        [
            'id'                 => 3,
            'edited'             => '2000-01-03 00:00:00',
            'guid'               => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
            'url_title_hash'     => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b',
            'url_content_hash'   => 'b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406',
            'title_content_hash' => 'ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
        ],
        [
            'id'                 => 4,
            'edited'             => '2000-01-04 00:00:00',
            'guid'               => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
            'url_title_hash'     => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8',
            'url_content_hash'   => 'f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3',
            'title_content_hash' => 'ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
        ],
        [
            'id'                 => 5,
            'edited'             => '2000-01-05 00:00:00',
            'guid'               => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
            'url_title_hash'     => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022',
            'url_content_hash'   => '834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900',
            'title_content_hash' => '43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
        ],
    ];
    protected $others = [
        [
            'id'                 => 6,
            'edited'             => '2000-01-06 00:00:00',
            'guid'               => 'b3461ab8e8759eeb1d65a818c65051ec00c1dfbbb32a3c8f6999434e3e3b76ab',
            'url_title_hash'     => '91d051a8e6749d014506848acd45e959af50bf876427c4f0e3a1ec0f04777b51',
            'url_content_hash'   => '211d78b1a040d40d17e747a363cc283f58767b2e502630d8de9b8f1d5e941d18',
            'title_content_hash' => '5ed68ccb64243b8c1931241d2c9276274c3b1d87f223634aa7a1ab0141292ca7',
        ],
        [
            'id'                 => 7,
            'edited'             => '2000-01-07 00:00:00',
            'guid'               => 'f4fae999d6531747523f4ff0c74f3f0c7c588b67e4f32d8f7dba5f6f36e8a45d',
            'url_title_hash'     => 'b92f805f0d0643dad1d6c0bb5cbaec24729f5f71b37b831cf7ad31f6c9403ac8',
            'url_content_hash'   => '4fc8789b787246e9be08ca1bac0d4a1ac4db1984f0db07f7142417598cf7211f',
            'title_content_hash' => '491df9338740b5297b3a3e8292be992ac112eb676c34595f7a38f3ee646ffe84',
        ],
        [
            'id'                 => 8,
            'edited'             => '2000-01-08 00:00:00',
            'guid'               => 'b9d2d58e3172096b1d23b42a59961fabc89962836c3cd5de54f3d3a98ff08e6c',
            'url_title_hash'     => '53a6cbcfeb66b46d09cbb7b25035df0562da35786933319c83b04be29acfb6f4',
            'url_content_hash'   => 'c6f3722b4445b49d19d39c3bf5b11a7cf23dd69873e2a0a458aab662f1cd9438',
            'title_content_hash' => '607d2da48807ca984ce2a9faa1d291bd9e3de9e912f83306167f4f5cd3c23bbd',
        ],
    ];

    public function setUp(): void {
        if (!@file_get_contents(self::$host."IsUp")) {
            $this->markTestSkipped("Test Web server is not accepting requests");
        }
        $this->base = self::$host."Feed/";
        parent::setUp();
        self::setConf();
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->feedMatchLatest->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->feedMatchLatest(1, $this->anything())->thenReturn(new Result($this->latest));
        \Phake::when(Arsse::$db)->feedMatchIds->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->feedMatchIds(1, \Phake::ignoreRemaining())->thenReturn(new Result($this->others));
    }

    public function testParseAFeed(): void {
        // test that various properties are set on the feed and on items
        $f = new Feed(null, $this->base."Parsing/Valid");
        $this->assertTrue(isset($f->lastModified));
        $this->assertTrue(isset($f->nextFetch));
        // check ID preference cascade
        $h0 = "0a4f0e3768c8a5e9d8d9a16545ae4ff5b097f6dac3ad49555a94a7cace68ba73"; // hash of Atom ID
        $h1 = "a135beced0236b723d12f845ff20ec22d4fc3afe1130012618f027170d57cb4e"; // hash of RSS2 GUID
        $h2 = "205e986f4f8b3acfa281227beadb14f5e8c32c8dae4737f888c94c0df49c56f8"; // hash of Dublin Core identifier
        $this->assertSame($h0, $f->items[0]->id);
        $this->assertSame($h1, $f->items[1]->id);
        $this->assertSame($h2, $f->items[2]->id);
        // check null hashes
        $h3 = "6287ba30f534e404e68356237e809683e311285d8b9f47d046ac58784eece052"; // URL hash
        $h4 = "6cbb5d2dcb11610a99eb3f633dc246690c0acf33327bf7534f95542caa8f27c4"; // title hash
        $h5 = "2b7c57ffa9adde92ccd1884fa1153a5bcd3211e48d99e27be5414cb078e6891c"; // content/enclosure hash
        $this->assertNotEquals("", $f->items[3]->urlTitleHash);
        $this->assertSame($h3, $f->items[3]->urlContentHash);
        $this->assertSame("", $f->items[3]->titleContentHash);
        $this->assertNotEquals("", $f->items[4]->urlTitleHash);
        $this->assertSame("", $f->items[4]->urlContentHash);
        $this->assertSame($h4, $f->items[4]->titleContentHash);
        $this->assertSame("", $f->items[5]->urlTitleHash);
        $this->assertNotEquals("", $f->items[5]->urlContentHash);
        $this->assertNotEquals("", $f->items[5]->titleContentHash);
        // check null IDs
        $this->assertSame(null, $f->items[3]->id);
        $this->assertSame(null, $f->items[4]->id);
        $this->assertSame(null, $f->items[5]->id);
        // check categories
        $categories = [
            "Aniki!",
            "Beams",
            "Bodybuilders",
            "Men",
        ];
        $this->assertSame([], $f->items[0]->categories);
        $this->assertSame([], $f->items[1]->categories);
        $this->assertSame([], $f->items[3]->categories);
        $this->assertSame([], $f->items[4]->categories);
        $this->assertSame($categories, $f->items[5]->categories);
    }

    public function testDiscoverAFeedSuccessfully(): void {
        $this->assertSame($this->base."Discovery/Feed", Feed::discover($this->base."Discovery/Valid"));
        $this->assertSame($this->base."Discovery/Feed", Feed::discover($this->base."Discovery/Feed"));
    }

    public function testDiscoverAFeedUnsuccessfully(): void {
        $this->assertException("subscriptionNotFound", "Feed");
        Feed::discover($this->base."Discovery/Invalid");
    }

    public function testDiscoverAMissingFeed(): void {
        $this->assertException("invalidUrl", "Feed");
        Feed::discover($this->base."Discovery/Missing");
    }

    public function testDiscoverMultipleFeedsSuccessfully(): void {
        $exp1 = [$this->base."Discovery/Feed", $this->base."Discovery/Missing"];
        $exp2 = [$this->base."Discovery/Feed"];
        $this->assertSame($exp1, Feed::discoverAll($this->base."Discovery/Valid"));
        $this->assertSame($exp2, Feed::discoverAll($this->base."Discovery/Feed"));
    }

    public function testDiscoverMultipleFeedsUnsuccessfully(): void {
        $this->assertSame([], Feed::discoverAll($this->base."Discovery/Invalid"));
    }

    public function testDiscoverMultipleMissingFeeds(): void {
        $this->assertException("invalidUrl", "Feed");
        Feed::discoverAll($this->base."Discovery/Missing");
    }

    public function testParseEntityExpansionAttack(): void {
        $this->assertException("xmlEntity", "Feed");
        new Feed(null, $this->base."Parsing/XEEAttack");
    }

    public function testParseExternalEntityAttack(): void {
        $this->assertException("xmlEntity", "Feed");
        new Feed(null, $this->base."Parsing/XXEAttack");
    }

    public function testParseAnUnsupportedFeed(): void {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Parsing/Unsupported");
    }

    public function testParseAMalformedFeed(): void {
        $this->assertException("malformedXml", "Feed");
        new Feed(null, $this->base."Parsing/Malformed");
    }

    public function testDeduplicateFeedItems(): void {
        // duplicates with dates lead to the newest match being kept
        $t = strtotime("2002-05-19T15:21:36Z");
        $f = new Feed(null, $this->base."Deduplication/Permalink-Dates");
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/ID-Dates");
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/IdenticalHashes");
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/Hashes-Dates1"); // content differs
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/Hashes-Dates2"); // title differs
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/Hashes-Dates3"); // URL differs
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        // duplicates without dates lead to the topmost entry being kept
        $f = new Feed(null, $this->base."Deduplication/Hashes");
        $this->assertCount(2, $f->newItems);
        $this->assertSame("http://example.com/1", $f->newItems[0]->url);
    }


    #[DataProvider('provide304ResponseURLs')]
    public function testHandleCacheHeadersOn304(string $url): void {
        // upon 304, the client should re-use the caching header values it supplied to the server
        $t = Date::transform("2010-01-01T00:00:00Z", "unix");
        $e = "78567a";
        $f = new Feed(null, $this->base.$url."?t=$t&e=$e", Date::transform($t, "http"), $e);
        $this->assertTime($t, $f->lastModified);
        $this->assertSame($e, $f->etag);
    }

    public static function provide304ResponseURLs() {
        return [
            'Control'                   => ["Caching/304Conditional"],
            'Random last-mod and ETag'  => ["Caching/304Random"],
            'ETag only'                 => ["Caching/304ETagOnly"],
            'Last-mod only'             => ["Caching/304LastModOnly"],
            'Neither last-mod nor ETag' => ["Caching/304None"],
        ];
    }

    public function testHandleCacheHeadersOn200(): void {
        // these tests should trust the server-returned time, even in cases of obviously incorrect results
        $t = time() - 2000;
        $f = new Feed(null, $this->base."Caching/200Past");
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->etag);
        $t = time() - 2000;
        $f = new Feed(null, $this->base."Caching/200Past", Date::transform(time(), "http"));
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->etag);
        $t = time() + 2000;
        $f = new Feed(null, $this->base."Caching/200Future");
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->etag);
        // these tests have no HTTP headers and rely on article dates
        $t = strtotime("2002-05-19T15:21:36Z");
        $f = new Feed(null, $this->base."Caching/200PubDateOnly");
        $this->assertTime($t, $f->lastModified);
        $f = new Feed(null, $this->base."Caching/200UpdateDate");
        $this->assertTime($t, $f->lastModified);
        $f = new Feed(null, $this->base."Caching/200Multiple");
        $this->assertTime($t, $f->lastModified);
        // this test has no dates at all and should report the current time
        $t = time();
        $f = new Feed(null, $this->base."Caching/200None");
        $this->assertTime($t, $f->lastModified);
    }

    public function testComputeNextFetchOnError(): void {
        for ($a = 0; $a < 100; $a++) {
            if ($a < 3) {
                $this->assertTime("now + 5 minutes", Feed::nextFetchOnError($a));
            } elseif ($a < 15) {
                $this->assertTime("now + 3 hours", Feed::nextFetchOnError($a));
            } else {
                $this->assertTime("now + 1 day", Feed::nextFetchOnError($a));
            }
        }
    }


    #[DataProvider('provide304Timestamps')]
    public function testComputeNextFetchFrom304(string $t, string $exp): void {
        $t = $t ? strtotime($t) : "";
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", Date::transform($t, "http"));
        $exp = strtotime($exp);
        $this->assertTime($exp, $f->nextFetch);
    }

    public static function provide304Timestamps(): iterable {
        return [
            'less than half an hour 1'     => ["now",                      "now + 15 minutes"],
            'less than half an hour 2'     => ["now - 29 minutes",         "now + 15 minutes"],
            'less than one hour 1'         => ["now - 30 minutes",         "now + 30 minutes"],
            'less than one hour 2'         => ["now - 59 minutes",         "now + 30 minutes"],
            'less than three hours 1'      => ["now - 1 hour",             "now + 1 hour"],
            'less than three hours 2'      => ["now - 2 hours 59 minutes", "now + 1 hour"],
            'more than thirty-six hours 1' => ["now - 36 hours",           "now + 1 day"],
            'more than thirty-six hours 2' => ["now - 2 years",            "now + 1 day"],
            'fallback 1'                   => ["now - 3 hours",            "now + 3 hours"],
            'fallback 2'                   => ["now - 35 hours",           "now + 3 hours"],
        ];
    }

    public function testComputeNextFetchFrom304WithoutDate(): void {
        $f = new Feed(null, $this->base."NextFetch/NotModifiedEtag");
        $exp = strtotime("now + 3 hours");
        $this->assertTime($exp, $f->nextFetch);
    }

    public function testComputeNextFetchFrom200(): void {
        // if less than half an hour, check in 15 minutes
        $f = new Feed(null, $this->base."NextFetch/30m");
        $exp = strtotime("now + 15 minutes");
        $this->assertTime($exp, $f->nextFetch);
        // if less than an hour, check in 30 minutes
        $f = new Feed(null, $this->base."NextFetch/1h");
        $exp = strtotime("now + 30 minutes");
        $this->assertTime($exp, $f->nextFetch);
        // if less than three hours, check in an hour
        $f = new Feed(null, $this->base."NextFetch/3h");
        $exp = strtotime("now + 1 hour");
        $this->assertTime($exp, $f->nextFetch);
        // if more than 36 hours, check in 24 hours
        $f = new Feed(null, $this->base."NextFetch/36h");
        $exp = strtotime("now + 24 hours");
        $this->assertTime($exp, $f->nextFetch);
        // otherwise check in three hours
        $f = new Feed(null, $this->base."NextFetch/3-36h");
        $exp = strtotime("now + 3 hours");
        $this->assertTime($exp, $f->nextFetch);
        // and if there is no common interval, check in an hour
        $f = new Feed(null, $this->base."NextFetch/Fallback");
        $exp = strtotime("now + 1 hour");
        $this->assertTime($exp, $f->nextFetch);
    }

    public function testMatchLatestArticles(): void {
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->feedMatchLatest(\Phake::anyParameters())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->feedMatchLatest(1, $this->anything())->thenReturn(new Result($this->latest));
        $f = new Feed(1, $this->base."Matching/1");
        $this->assertCount(0, $f->newItems);
        $this->assertCount(0, $f->changedItems);
        $f = new Feed(1, $this->base."Matching/2");
        $this->assertCount(1, $f->newItems);
        $this->assertCount(0, $f->changedItems);
        $f = new Feed(1, $this->base."Matching/3");
        $this->assertCount(1, $f->newItems);
        $this->assertCount(2, $f->changedItems);
        $f = new Feed(1, $this->base."Matching/4");
        $this->assertCount(1, $f->newItems);
        $this->assertCount(2, $f->changedItems);
    }

    public function testMatchHistoricalArticles(): void {
        $f = new Feed(1, $this->base."Matching/5");
        $this->assertCount(0, $f->newItems);
        $this->assertCount(0, $f->changedItems);
    }

    public function testScrapeFullContent(): void {
        // first make sure that the absence of scraping works as expected
        $f = new Feed(null, $this->base."Scraping/Feed");
        $exp = "<p>Partial content</p>";
        $this->assertSame($exp, $f->newItems[0]->content);
        // now try to scrape and get different content
        $f = new Feed(null, $this->base."Scraping/Feed", "", "", null, null, true);
        $exp = "<p>Partial content, followed by more content</p>";
        $this->assertSame($exp, $f->newItems[0]->scrapedContent);
        $exp = "<p>Partial content</p>";
        $this->assertSame($exp, $f->newItems[0]->content);
    }

    public function testScrapeFullContentWithError(): void {
        // this should not throw any exceptions
        $f = new Feed(null, $this->base."Scraping/Partial", "", "", null, null, true);
        $exp1 = "<p>Partial content, followed by more content</p>";
        $exp2 = "<p>Partial content</p>";
        $this->assertSame($exp1, $f->newItems[1]->scrapedContent);
        $this->assertSame($exp2, $f->newItems[1]->content);
        $this->assertSame(null, $f->newItems[0]->scrapedContent);
        $this->assertSame($exp2, $f->newItems[0]->content);
    }

    public function testScrapeFullExplicitly(): void {
        $act = Feed::scrapeSingle($this->base."Scraping/Document", $this->base."Scraping/Document");
        $exp = "<p>Partial content, followed by more content</p>";
        $this->assertSame($exp, $act);
    }

    public function testScrapeFullExplicitlyWithoutContent(): void {
        $act = Feed::scrapeSingle($this->base."Discovery/Valid", $this->base."Scraping/Document");
        $this->assertSame("", $act);
    }

    public function testScrapeFullExplicitlyWithError(): void {
        $this->expectException(FeedException::class);
        Feed::scrapeSingle($this->base."Fetching/Error?code=404", $this->base."Scraping/Partial");
    }

    public function testFetchWithIcon(): void {
        $d = base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==");
        $f = new Feed(null, $this->base."WithIcon/GIF");
        $this->assertSame(self::$host."Icon/GIF", $f->iconUrl);
        $this->assertSame("image/gif", $f->iconType);
        $this->assertSame($d, $f->iconData);
    }
}
