<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Feed;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Misc\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
#[CoversClass(\JKingWeb\Arsse\Feed::class)]
class TestFetching extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $host = "http://localhost:8000/";
    protected $base = "";

    public function setUp(): void {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped("Feed fetching tests are only accurate with curl enabled.");
        } elseif (!@file_get_contents(self::$host."IsUp")) {
            $this->markTestSkipped("Test Web server is not accepting requests");
        }
        $this->base = self::$host."Feed/";
        parent::setUp();
        self::setConf();
    }

    public function testHandle400(): void {
        $this->assertException("transmissionError", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=400");
    }

    public function testHandle401(): void {
        $this->assertException("unauthorized", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=401");
    }

    public function testHandle403(): void {
        $this->assertException("forbidden", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=403");
    }

    public function testHandle404(): void {
        $this->assertException("invalidUrl", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=404");
    }

    public function testHandle500(): void {
        $this->assertException("transmissionError", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=500");
    }

    public function testHandleARedirectLoop(): void {
        $this->assertException("maxRedirect", "Feed");
        new Feed(null, $this->base."Fetching/EndlessLoop?i=0");
    }

    public function testHandleAnOverlyLargeFeed(): void {
        $this->markTestIncomplete("The nicolus/picofeed library does not implement miniflux/picofeed's max-size setting");
        Arsse::$conf->fetchSizeLimit = 512;
        $this->assertException("maxSize", "Feed");
        new Feed(null, $this->base."Fetching/TooLarge");
    }

    public function testHandleACertificateError(): void {
        $this->assertException("invalidCertificate", "Feed");
        new Feed(null, "https://localhost:8000/");
    }

    public function testHandleATimeout(): void {
        Arsse::$conf->fetchTimeout = 1;
        $this->assertException("timeout", "Feed");
        new Feed(null, $this->base."Fetching/Timeout");
    }

    public function testFetchWithCustomUserAgent(): void {
        $exp = "Custom";
        $f = new Feed(null, $this->base."Fetching/UserAgent", "", "", $exp);
        $this->assertSame($exp, $f->title);
        $this->assertSame("<p>Partial content</p>", $f->items[0]->content);
        // Scraping should also use the custom UA
        $f = new Feed(null, $this->base."Fetching/UserAgent", "", "", "Custom", null, true);
        $this->assertSame($exp, $f->title);
        $this->assertSame($exp, $f->items[0]->scrapedContent);
        // Explicit scraping should, too
        $this->assertSame($exp, Feed::scrapeSingle($this->base."Scraping/UserAgent", $this->base."Fetching/UserAgent", $exp, null));
    }

    public function testFetchWithCookie(): void {
        $exp = '{"a":"b","c":"d"}';
        $in = "a=b; c=d";
        $f = new Feed(null, $this->base."Fetching/Cookie", "", "", null, $in);
        $this->assertSame($exp, $f->title);
        $this->assertSame("<p>Partial content</p>", $f->items[0]->content);
        // Scraping should also use the cookie
        $f = new Feed(null, $this->base."Fetching/Cookie", "", "", null, $in, true);
        $this->assertSame($exp, $f->title);
        $this->assertSame(htmlspecialchars($exp), $f->items[0]->scrapedContent);
        // Explicit scraping should, too
        $this->assertSame(htmlspecialchars($exp), Feed::scrapeSingle($this->base."Scraping/Cookie", $this->base."Fetching/Cookie", null, $in));
    }

    public function testFetchWithCredentials(): void {
        $url = URL::normalize($this->base."Scraping/FeedPW", "user", "pass");
        $f = new Feed(null, $url, "", "", null, "a=b; c=d");
        $this->assertSame('Test feed', $f->title);
        $this->assertSame("<p>Partial content</p>", $f->items[0]->content);
        // Scraping should also use the credentials
        $f = new Feed(null, $url, "", "", null, "a=b; c=d", true);
        $this->assertSame('Test feed', $f->title);
        $this->assertSame("<p>Partial content, followed by more content</p>", $f->items[0]->scrapedContent);
        // Explicit scraping should, too
        $this->assertSame("<p>Partial content, followed by more content</p>", Feed::scrapeSingle($this->base."Scraping/DocumentPW", $url));
    }
}
