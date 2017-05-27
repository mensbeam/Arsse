<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
Use Phake;


class TestFeed extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected static $host = "http://localhost:8000/";
    protected $base = "";

    function setUp() {
        if(!@file_get_contents(self::$host."IsUp")) {
            $this->markTestSkipped("Test Web server is not accepting requests");
        } else if(!extension_loaded('curl')) {
            $this->markTestSkipped("Feed tests are only accurate with curl enabled.");
        }
        $this->base = self::$host."Feed/";
        $this->clearData();
        Data::$conf = new Conf();
    }

    function testHandle400() {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=400");
    }

    function testHandle401() {
        $this->assertException("unauthorized", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=401");
    }

    function testHandle403() {
        $this->assertException("forbidden", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=403");
    }

    function testHandle404() {
        $this->assertException("invalidUrl", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=404");
    }

    function testHandle500() {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=500");
    }

    function testHandleARedirectLoop() {
        $this->assertException("maxRedirect", "Feed");
        new Feed(null, $this->base."Fetching/EndlessLoop?i=0");
    }

    function testHandleATimeout() {
        Data::$conf->fetchTimeout = 1;
        $this->assertException("timeout", "Feed");
        new Feed(null, $this->base."Fetching/Timeout");
    }

    function testHandleAnOverlyLargeFeed() {
        Data::$conf->fetchSizeLimit = 512;
        $this->assertException("maxSize", "Feed");
        new Feed(null, $this->base."Fetching/TooLarge");
    }

    function testHandleACertificateError() {
        $this->assertException("invalidCertificate", "Feed");
        new Feed(null, "https://localhost:8000/");
    }

    function testParseAFeed() {
        // test that various properties are set on the feed and on items
        $f = new Feed(null, $this->base."Parsing/Valid");
        $this->assertTrue(isset($f->lastModified));
        $this->assertTrue(isset($f->nextFetch));
        // check ID preference cascade
        $h0 = "0a4f0e3768c8a5e9d8d9a16545ae4ff5b097f6dac3ad49555a94a7cace68ba73"; // hash of Atom ID
        $h1 = "a135beced0236b723d12f845ff20ec22d4fc3afe1130012618f027170d57cb4e"; // hash of RSS2 GUID
        $h2 = "205e986f4f8b3acfa281227beadb14f5e8c32c8dae4737f888c94c0df49c56f8"; // hash of Dublin Core identifier
        $this->assertSame($h0, $f->data->items[0]->id);
        $this->assertSame($h1, $f->data->items[1]->id);
        $this->assertSame($h2, $f->data->items[2]->id);
        // check null hashes
        $h3 = "6287ba30f534e404e68356237e809683e311285d8b9f47d046ac58784eece052"; // URL hash
        $h4 = "6cbb5d2dcb11610a99eb3f633dc246690c0acf33327bf7534f95542caa8f27c4"; // title hash
        $h5 = "2b7c57ffa9adde92ccd1884fa1153a5bcd3211e48d99e27be5414cb078e6891c"; // content/enclosure hash
        $this->assertNotEquals("", $f->data->items[3]->urlTitleHash);
        $this->assertSame($h3, $f->data->items[3]->urlContentHash);
        $this->assertSame("", $f->data->items[3]->titleContentHash);
        $this->assertNotEquals("", $f->data->items[4]->urlTitleHash);
        $this->assertSame("", $f->data->items[4]->urlContentHash);
        $this->assertSame($h4, $f->data->items[4]->titleContentHash);
        $this->assertSame("", $f->data->items[5]->urlTitleHash);
        $this->assertNotEquals("", $f->data->items[5]->urlContentHash);
        $this->assertNotEquals("", $f->data->items[5]->titleContentHash);
        // check null IDs
        $this->assertSame("", $f->data->items[3]->id);
        $this->assertSame("", $f->data->items[4]->id);
        $this->assertSame("", $f->data->items[5]->id);
    }

    function testParseEntityExpansionAttack() {
        $this->assertException("xmlEntity", "Feed");
        new Feed(null, $this->base."Parsing/XEEAttack");
    }

    function testParseExternalEntityAttack() {
        $this->assertException("xmlEntity", "Feed");
        new Feed(null, $this->base."Parsing/XXEAttack");
    }

    function testParseAnUnsupportedFeed() {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Parsing/Unsupported");
    }

    function testParseAMalformedFeed() {
        $this->assertException("malformedXml", "Feed");
        new Feed(null, $this->base."Parsing/Malformed");
    }
    
    function testDeduplicateFeedItems() {
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

    function testHandleCacheHeadersOn304() {
        // upon 304, the client should re-use the caching header values it supplied the server
        $t = time();
        $e = "78567a";
        $f = new Feed(null, $this->base."Caching/304Random", $this->dateTransform($t, "http"), $e);
        $this->assertTime($t, $f->lastModified);
        $this->assertSame($e, $f->resource->getETag());
        $f = new Feed(null, $this->base."Caching/304ETagOnly", $this->dateTransform($t, "http"), $e);
        $this->assertTime($t, $f->lastModified);
        $this->assertSame($e, $f->resource->getETag());
        $f = new Feed(null, $this->base."Caching/304LastModOnly", $this->dateTransform($t, "http"), $e);
        $this->assertTime($t, $f->lastModified);
        $this->assertSame($e, $f->resource->getETag());
        $f = new Feed(null, $this->base."Caching/304None", $this->dateTransform($t, "http"), $e);
        $this->assertTime($t, $f->lastModified);
        $this->assertSame($e, $f->resource->getETag());
    }

    function testHandleCacheHeadersOn200() {
        // these tests should trust the server-returned time, even in cases of obviously incorrect results
        $t = time() - 2000;
        $f = new Feed(null, $this->base."Caching/200Past");
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->resource->getETag());
        $t = time() - 2000;
        $f = new Feed(null, $this->base."Caching/200Past", $this->dateTransform(time(), "http"));
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->resource->getETag());
        $t = time() + 2000;
        $f = new Feed(null, $this->base."Caching/200Future");
        $this->assertTime($t, $f->lastModified);
        $this->assertNotEmpty($f->resource->getETag());
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
    
    function testComputeNextFetchOnError() {
        for($a = 0; $a < 100; $a++) {
            if($a < 3) {
                $this->assertTime("now + 5 minutes", Feed::nextFetchOnError($a));
            } else if($a < 15) {
                $this->assertTime("now + 3 hours", Feed::nextFetchOnError($a));
            } else {
                $this->assertTime("now + 1 day", Feed::nextFetchOnError($a));
            }
        }
    }
    
    function testComputeNextFetchFrom304() {
        // if less than half an hour, check in 15 minutes
        $t = strtotime("now");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 15 minutes");
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 29 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 15 minutes");
        $this->assertTime($exp, $f->nextFetch);
        // if less than an hour, check in 30 minutes
        $t = strtotime("now - 30 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 30 minutes");
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 59 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 30 minutes");
        $this->assertTime($exp, $f->nextFetch);
        // if less than three hours, check in an hour
        $t = strtotime("now - 1 hour");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 1 hour");
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 2 hours 59 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 1 hour");
        $this->assertTime($exp, $f->nextFetch);
        // if more than 36 hours, check in 24 hours
        $t = strtotime("now - 36 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 1 day");
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 2 years");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 1 day");
        $this->assertTime($exp, $f->nextFetch);
        // otherwise check in three hours
        $t = strtotime("now - 3 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 3 hours");
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 35 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $exp = strtotime("now + 3 hours");
        $this->assertTime($exp, $f->nextFetch);
    }
}