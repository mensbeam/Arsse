<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\Misc\URL::class)]
class TestURL extends \JKingWeb\Arsse\Test\AbstractTest {
    #[DataProvider('provideNormalizations')]
    public function testNormalizeAUrl(string $url, ?string $user, ?string $pass, string $exp): void {
        $this->assertSame($exp, URL::normalize($url, $user, $pass));
    }

    public static function provideNormalizations(): iterable {
        return [
            ["http://example.com/",           null,   null,   "http://example.com/"],
            ["HTTP://example.com/",           null,   null,   "http://example.com/"],
            ["http://example.com",            null,   null,   "http://example.com/"],
            ["http://example.com:/",          null,   null,   "http://example.com/"],
            ["HTTP://example.com:80/",        null,   null,   "http://example.com/"],
            ["HTTP://example.com:80",         null,   null,   "http://example.com/"],
            ["http://example.com/?",          null,   null,   "http://example.com/"],
            ["http://example.com?",           null,   null,   "http://example.com/"],
            ["http://example.com/#fragment",  null,   null,   "http://example.com/"],
            ["http://example.com#fragment",   null,   null,   "http://example.com/"],
            ["http://example.com?#",          null,   null,   "http://example.com/"],
            ["http://example.com/?key=value", null,   null,   "http://example.com/?key=value"],
            ["http://example.com/",           "user", "pass", "http://user:pass@example.com/"],
            ["http://example.com/",           "user", null,   "http://user@example.com/"],
            ["http://user:pass@example.com/", null,   null,   "http://user:pass@example.com/"],
            ["http://user@example.com/",      null,   null,   "http://user@example.com/"],
            ["http://user:pass@example.com/", "u",    "p",    "http://u:p@example.com/"],
            ["http://user:pass@example.com/", "u",    "",     "http://u@example.com/"],
            ["http://user:pass@example.com/", "",     "p",    "http://:p@example.com/"],
            ["http://user:pass@example.com/", "",     null,   "http://:pass@example.com/"],
            ["http://example.com/",           "",     "p",    "http://:p@example.com/"],
            ["http://user:pass@example.com/", "u",    null,   "http://u:pass@example.com/"],
            ["http://user:pass@example.com/", null,   "p",    "http://user:p@example.com/"],
            ["http://user:pass@example.com/", null,   "",     "http://user@example.com/"],
            ["http://example.com/path",       null,   null,   "http://example.com/path"],
            ["http://example.com/PATH",       null,   null,   "http://example.com/PATH"],
            ["http://example.com/path/",      null,   null,   "http://example.com/path/"],
            ["http://example.com/path/.",     null,   null,   "http://example.com/path/"],
            ["http://example.com/path/./",    null,   null,   "http://example.com/path/"],
            ["http://example.com/path/..",    null,   null,   "http://example.com/"],
            ["http://example.com/path/../",   null,   null,   "http://example.com/"],
            ["http://example.com/a/b/..",     null,   null,   "http://example.com/a/"],
            ["http://example.com/a/b/../",    null,   null,   "http://example.com/a/"],
            ["http://example.com/../",        null,   null,   "http://example.com/"],
            ["http://example.com////",        null,   null,   "http://example.com////"],
            ["http://example.com/a/./b/",     null,   null,   "http://example.com/a/b/"],
            ["http://example.com/a/../b/",    null,   null,   "http://example.com/b/"],
            ["http://example.com/.a/",        null,   null,   "http://example.com/.a/"],
            ["http://example.com/..a/",       null,   null,   "http://example.com/..a/"],
            ["http://日本.example.com/",      null,   null,   "http://xn--wgv71a.example.com/"],
            ["http://EXAMPLE.COM/",           null,   null,   "http://example.com/"],
            ["http://É.example.com/",         null,   null,   "http://xn--9ca.example.com/"],
            ["http://[::1]/",                 null,   null,   "http://[::1]/"],
            ["http://[0::1]/",                null,   null,   "http://[::1]/"],
            ["http://[Z]/",                   null,   null,   "http://[Z]/"],
            ["http://example.com/ ?%61=%3d",  null,   null,   "http://example.com/%20?a=%3D"],
            ["http://example.com/%",          null,   null,   "http://example.com/%25"],
            ["http://example.com/%a",         null,   null,   "http://example.com/%25a"],
            ["http://example.com/%za",        null,   null,   "http://example.com/%25za"],
            ["//EXAMPLE.COM/",                null,   null,   "//example.com/"],
            ["//EXAMPLE.COM/",                "u",    "p",    "//u:p@example.com/"],
            ["/ ",                            null,   null,   "/"],
            ["/ ",                            "u",    "p",    "/"],
            ["EXAMPLE.COM/",                  null,   null,   "EXAMPLE.COM/"],
            ["EXAMPLE.COM",                   null,   null,   "EXAMPLE.COM"],
            [" ",                             null,   null,   " "],            
        ];
    }


    #[DataProvider('provideQueries')]
    public function testAppendQueryParameters(string $url, string $query, string $exp): void {
        $this->assertSame($exp, URL::queryAppend($url, $query));
    }

    public static function provideQueries(): iterable {
        return [
            ["/", "ook=eek", "/?ook=eek"],
            ["/?", "ook=eek", "/?ook=eek"],
            ["/#ack", "ook=eek", "/?ook=eek#ack"],
            ["/?Huh?", "ook=eek", "/?Huh?&ook=eek"],
            ["/?Eh?&Huh?&", "ook=eek", "/?Eh?&Huh?&ook=eek"],
            ["/#ack", "", "/#ack"],
        ];
    }


    #[DataProvider('provideAbsolutes')]
    public function testDetermineAbsoluteness(bool $exp, string $url): void {
        $this->assertSame($exp, URL::absolute($url));
    }

    public static function provideAbsolutes(): array {
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

    #[DataProvider("provideCredentials")]
    public function testApplyCredentials(string $src, string $dst, string $exp): void {
        $this->assertSame($exp, URL::credentialsApply($dst, $src));
    }

    public static function provideCredentials(): iterable {
        return [
            ["http://example.com/src",        "http://example.com/dst", "http://example.com/dst"],
            ["http://u:p@example.com/src",    "http://example.com/dst", "http://u:p@example.com/dst"],
            ["HTTP://u:p@EXAMPLE.COM/src",    "http://example.com/dst", "http://u:p@example.com/dst"],
            ["https://example.com/src",       "http://example.com/dst", "http://example.com/dst"],
            ["https://u:p@example.com/src",   "http://example.com/dst", "http://example.com/dst"],
            ["http://u:p@example.net/src",    "http://example.com/dst", "http://example.com/dst"],
            ["http://u:p@example.com:80/src", "http://example.com/dst", "http://u:p@example.com/dst"],
            ["//u:p@example.com/src",         "http://example.com/dst", "http://example.com/dst"],
            ["/src",                          "http://example.com/dst", "http://example.com/dst"],
        ];
    }

    #[DataProvider('provideUnnormalizedOrigins')]
    public function testNormalizeOrigins(string $origin, string $exp, ?array $ports = null): void {
        $act = URL::origin($origin);
        $this->assertSame($exp, $act);
    }

    public static function provideUnnormalizedOrigins(): iterable {
        return [
            ["null", "null"],
            ["http://example.com",             "http://example.com"],
            ["http://example.com:80",          "http://example.com"],
            ["http://example.com:8080",        "http://example.com:8080"],
            ["http://[2001:0db8:0:0:0:0:2:1]", "http://[2001:db8::2:1]"],
            ["http://example",                 "http://example"],
            ["http://ex%41mple",               "http://example"],
            ["http://ex%41mple.co.uk",         "http://example.co.uk"],
            ["http://ex%41mple.co%2euk",       "http://example.co.uk"],
            ["http://example/",                "http://example"],
            ["http://example?",                "http://example"],
            ["http://example#",                "http://example"],
            ["http://user@example",            "http://example"],
            ["http://user:pass@example",       "http://example"],
            ["http://[example",                ""],
            ["http://[2bef]",                  ""],
            ["http://example%2F",              ""],
            ["HTTP://example",                 "http://example"],
            ["HTTP://EXAMPLE",                 "http://example"],
            ["%48%54%54%50://example",         ""],
            ["http:%2F%2Fexample",             ""],
            ["https://example",                "https://example"],
            ["https://example:443",            "https://example"],
            ["https://example:80",             "https://example:80"],
            ["ssh://example",                  "ssh://example"],
            ["ssh://example:22",               "ssh://example:22"],
        ];
    }
}
