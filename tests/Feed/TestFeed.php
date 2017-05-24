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
        }
        $this->base = self::$host."Feed/";
        $this->clearData();
        Data::$conf = new Conf();
    }

    function testDeduplicateFeedItems() {
        $t = strtotime("2002-05-19T15:21:36Z");
        $f = new Feed(null, $this->base."Deduplication/Permalink-Dates");
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
        $f = new Feed(null, $this->base."Deduplication/ID-Dates");
        $this->assertCount(2, $f->newItems);
        $this->assertTime($t, $f->newItems[0]->updatedDate);
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