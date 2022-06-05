<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\URL;

/** @covers \JKingWeb\Arsse\Misc\URL */
class TestURL extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideNormalizations */
    public function testNormalizeAUrl(string $url, string $exp, string $user = null, string $pass = null): void {
        $this->assertSame($exp, URL::normalize($url, $user, $pass));
    }

    public function provideNormalizations(): iterable {
        return [
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
            ["http://example.com/PATH",       "http://example.com/PATH"],
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
            ["//EXAMPLE.COM/",                "//example.com/"],
            ["//EXAMPLE.COM/",                "//u:p@example.com/", "u", "p"],
            ["/ ",                            "/%20"],
            ["/ ",                            "/%20", "u", "p"],
            ["EXAMPLE.COM/",                  "EXAMPLE.COM/"],
            ["EXAMPLE.COM",                   "EXAMPLE.COM"],
            [" ",                             "%20"],
        ];
    }

    /** @dataProvider provideQueries */
    public function testAppendQueryParameters(string $url, string $query, string $exp): void {
        $this->assertSame($exp, URL::queryAppend($url, $query));
    }

    public function provideQueries(): iterable {
        return [
            ["/", "ook=eek", "/?ook=eek"],
            ["/?", "ook=eek", "/?ook=eek"],
            ["/#ack", "ook=eek", "/?ook=eek#ack"],
            ["/?Huh?", "ook=eek", "/?Huh?&ook=eek"],
            ["/?Eh?&Huh?&", "ook=eek", "/?Eh?&Huh?&ook=eek"],
            ["/#ack", "", "/#ack"],
        ];
    }

    /** @dataProvider provideAbsolutes */
    public function testDetermineAbsoluteness(bool $exp, string $url): void {
        $this->assertSame($exp, URL::absolute($url));
    }

    public function provideAbsolutes(): array {
        return [
            [true,  "http://example.com/"],
            [true,  "HTTP://example.com/"],
            [false, "//example.com/"],
            [false, "/example"],
            [false, "example.com/"],
            [false, "example.com"],
            [false, "http:///example"],
        ];
    }
}
