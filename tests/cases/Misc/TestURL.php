<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\URL;

/** @covers \JKingWeb\Arsse\Misc\URL */
class TestURL extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        self::clearData();
    }
    
    /** @dataProvider provideNormalizations */
    public function testNormalizeAUrl(string $url, string $exp, string $user = null, string $pass = null) {
        $this->assertSame($exp, URL::normalize($url, $user, $pass));
    }

    public function provideNormalizations() {
        return [
            ["/",                             "/"],
            ["//example.com/",                "//example.com/"],
            ["/ ",                             "/ "],
            ["//EXAMPLE.COM/",                "//EXAMPLE.COM/"],
            ["http://example.com/",           "http://example.com/"],
            ["HTTP://example.com/",           "http://example.com/"],
            ["http://example.com",            "http://example.com/"],
            ["http://example.com:/",          "http://example.com/"],
            ["HTTP://example.com:80/",        "http://example.com:80/"],
            ["HTTP://example.com:80",         "http://example.com:80/"],
            ["http://example.com/?",          "http://example.com/"],
            ["http://example.com?",           "http://example.com/"],
            ["http://example.com/#fragment",  "http://example.com/"],
            ["http://example.com#fragment",   "http://example.com/"],
            ["http://example.com?#",          "http://example.com/"],
            ["http://example.com/?key=value", "http://example.com/?key=value"],
            ["http://example.com/",           "http://user:pass@example.com/", "user", "pass"],
            ["http://example.com/",           "http://user@example.com/", "user"],
            ["http://user:pass@example.com/", "http://user:pass@example.com/"],
            ["http://user@example.com/",      "http://user@example.com/"],
            ["http://user:pass@example.com/", "http://u:p@example.com/", "u", "p"],
            ["http://user:pass@example.com/", "http://u@example.com/", "u"],
            ["http://user:pass@example.com/", "http://user:pass@example.com/", "", "p"],
            ["http://example.com/",           "http://example.com/", "", "p"],
            ["http://example.com/path",       "http://example.com/path"],
            ["http://example.com/path/",      "http://example.com/path/"],
            ["http://example.com/path/.",     "http://example.com/path"],
            ["http://example.com/path/./",    "http://example.com/path/"],
            ["http://example.com/path/..",    "http://example.com/"],
            ["http://example.com/path/../",   "http://example.com/"],
            ["http://example.com/a/b/..",     "http://example.com/a"],
            ["http://example.com/a/b/../",    "http://example.com/a/"],
            ["http://example.com/../",        "http://example.com/"],
            ["http://example.com////",        "http://example.com/"],
            ["http://example.com/a/./b/",     "http://example.com/a/b/"],
            ["http://example.com/a/../b/",    "http://example.com/b/"],
            ["http://example.com/.a/",        "http://example.com/.a/"],
            ["http://example.com/..a/",       "http://example.com/..a/"],
            ["http://日本.example.com/",      "http://日本.example.com/"],
            ["http://EXAMPLE.COM/",           "http://example.com/"],
            ["http://É.example.com/",         "http://é.example.com/"],
            ["http://[::1]/",                 "http://[::1]/"],
            ["http://[0::1]/",                "http://[::1]/"],
            ["http://[Z]/",                   "http://[z]/"],
            ["http://example.com/ ?%61=%3d",  "http://example.com/%20?a=%3D"],
            ["http://example.com/%",          "http://example.com/%25"],
            ["http://example.com/%a",         "http://example.com/%25a"],
            ["http://example.com/%za",        "http://example.com/%25za"],
        ];
    }
}
