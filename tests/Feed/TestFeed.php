<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
Use Phake;


class TestFeed extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected static $host = "http://localhost:8000/";
    protected $base = "";
    protected $latest = [
        [
            'id' => 1,
            'edited_date' => 946684800,
            'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
            'url_title_hash' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6',
            'url_content_hash' => 'fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4',
            'title_content_hash' => '18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
        ],
        [
            'id' => 2,
            'edited_date' => 946771200,
            'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
            'url_title_hash' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153',
            'url_content_hash' => '13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9',
            'title_content_hash' => '2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
        ],
        [
            'id' => 3,
            'edited_date' => 946857600,
            'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
            'url_title_hash' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b',
            'url_content_hash' => 'b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406',
            'title_content_hash' => 'ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
        ],
        [
            'id' => 4,
            'edited_date' => 946944000,
            'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
            'url_title_hash' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8',
            'url_content_hash' => 'f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3',
            'title_content_hash' => 'ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
        ],
        [
            'id' => 5,
            'edited_date' => 947030400,
            'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
            'url_title_hash' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022',
            'url_content_hash' => '834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900',
            'title_content_hash' => '43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
        ],
    ];
    protected $others = [
        [
            'id' => 6,
            'edited_date' => 947116800,
            'guid' => 'b3461ab8e8759eeb1d65a818c65051ec00c1dfbbb32a3c8f6999434e3e3b76ab',
            'url_title_hash' => '91d051a8e6749d014506848acd45e959af50bf876427c4f0e3a1ec0f04777b51',
            'url_content_hash' => '211d78b1a040d40d17e747a363cc283f58767b2e502630d8de9b8f1d5e941d18',
            'title_content_hash' => '5ed68ccb64243b8c1931241d2c9276274c3b1d87f223634aa7a1ab0141292ca7',
        ],
        [
            'id' => 7,
            'edited_date' => 947203200,
            'guid' => 'f4fae999d6531747523f4ff0c74f3f0c7c588b67e4f32d8f7dba5f6f36e8a45d',
            'url_title_hash' => 'b92f805f0d0643dad1d6c0bb5cbaec24729f5f71b37b831cf7ad31f6c9403ac8',
            'url_content_hash' => '4fc8789b787246e9be08ca1bac0d4a1ac4db1984f0db07f7142417598cf7211f',
            'title_content_hash' => '491df9338740b5297b3a3e8292be992ac112eb676c34595f7a38f3ee646ffe84',
        ],
        [
            'id' => 8,
            'edited_date' => 947289600,
            'guid' => 'b9d2d58e3172096b1d23b42a59961fabc89962836c3cd5de54f3d3a98ff08e6c',
            'url_title_hash' => '53a6cbcfeb66b46d09cbb7b25035df0562da35786933319c83b04be29acfb6f4',
            'url_content_hash' => 'c6f3722b4445b49d19d39c3bf5b11a7cf23dd69873e2a0a458aab662f1cd9438',
            'title_content_hash' => '607d2da48807ca984ce2a9faa1d291bd9e3de9e912f83306167f4f5cd3c23bbd',
        ],
    ];

    function setUp() {
        if(!@file_get_contents(self::$host."IsUp")) {
            $this->markTestSkipped("Test Web server is not accepting requests");
        } else if(!extension_loaded('curl')) {
            $this->markTestSkipped("Feed tests are only accurate with curl enabled.");
        }
        $this->base = self::$host."Feed/";
        $this->clearData();
        Data::$conf = new Conf();
        Data::$db = Phake::mock(Database::class);
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
        $this->assertSame(null, $f->data->items[3]->id);
        $this->assertSame(null, $f->data->items[4]->id);
        $this->assertSame(null, $f->data->items[5]->id);
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
    
    function testComputeNextFetchFrom200() {
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

    function testMatchLatestArticles() {
        Phake::when(Data::$db)->feedMatchLatest(1, $this->anything())->thenReturn(new Test\Result($this->latest));
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

    function testMatchHistoricalArticles() {
        Phake::when(Data::$db)->feedMatchLatest(1, $this->anything())->thenReturn(new Test\Result($this->latest));
        Phake::when(Data::$db)->feedMatchIds(1, $this->anything(), $this->anything(), $this->anything(), $this->anything())->thenReturn(new Test\Result($this->others));
        $f = new Feed(1, $this->base."Matching/5");
        $this->assertCount(0, $f->newItems);
        $this->assertCount(0, $f->changedItems);   
    }
}