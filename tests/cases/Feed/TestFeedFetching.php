<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use Phake;

/** @covers \JKingWeb\Arsse\Feed */
class TestFeedFetching extends Test\AbstractTest {
    protected static $host = "http://localhost:8000/";
    protected $base = "";

    public function setUp() {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped("Feed fetching tests are only accurate with curl enabled.");
        } elseif (!@file_get_contents(self::$host."IsUp")) {
            $this->markTestSkipped("Test Web server is not accepting requests");
        }
        $this->base = self::$host."Feed/";
        $this->clearData();
        Arsse::$conf = new Conf();
    }

    public function testHandle400() {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=400");
    }

    public function testHandle401() {
        $this->assertException("unauthorized", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=401");
    }

    public function testHandle403() {
        $this->assertException("forbidden", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=403");
    }

    public function testHandle404() {
        $this->assertException("invalidUrl", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=404");
    }

    public function testHandle500() {
        $this->assertException("unsupportedFeedFormat", "Feed");
        new Feed(null, $this->base."Fetching/Error?code=500");
    }

    public function testHandleARedirectLoop() {
        $this->assertException("maxRedirect", "Feed");
        new Feed(null, $this->base."Fetching/EndlessLoop?i=0");
    }

    public function testHandleAnOverlyLargeFeed() {
        Arsse::$conf->fetchSizeLimit = 512;
        $this->assertException("maxSize", "Feed");
        new Feed(null, $this->base."Fetching/TooLarge");
    }

    public function testHandleACertificateError() {
        $this->assertException("invalidCertificate", "Feed");
        new Feed(null, "https://localhost:8000/");
    }

    public function testHandleATimeout() {
        Arsse::$conf->fetchTimeout = 1;
        $this->assertException("timeout", "Feed");
        new Feed(null, $this->base."Fetching/Timeout");
    }
}
