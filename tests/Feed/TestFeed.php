<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
Use Phake;


class TestFeed extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $base = "http://localhost:8000/Feed/";

    function time(string $t): string {
        return gmdate("D, d M Y H:i:s \G\M\T", strtotime($t));
    }

    function setUp() {
        $this->clearData();
        Data::$conf = new Conf();
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
        $exp = strtotime("now + 15 minutes");
        $t = strtotime("now");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 29 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        // if less than an hour, check in 30 minutes
        $exp = strtotime("now + 30 minutes");
        $t = strtotime("now - 30 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 59 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        // if less than three hours, check in an hour
        $exp = strtotime("now + 1 hour");
        $t = strtotime("now - 1 hour");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 2 hours 59 minutes");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        // if more than 36 hours, check in 24 hours
        $exp = strtotime("now + 1 day");
        $t = strtotime("now - 36 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 2 years");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        // otherwise check in three hours
        $exp = strtotime("now + 3 hours");
        $t = strtotime("now - 3 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
        $t = strtotime("now - 35 hours");
        $f = new Feed(null, $this->base."NextFetch/NotModified?t=$t", $this->dateTransform($t, "http"));
        $this->assertTime($exp, $f->nextFetch);
    }
}