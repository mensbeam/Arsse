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
            ["HTTP://example.com:80/",        null,   null,   "http://example.com:80/"],
            ["HTTP://example.com:80",         null,   null,   "http://example.com:80/"],
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
            ["http://user:pass@example.com/", "",     "p",    "http://example.com/"],
            ["http://user:pass@example.com/", "",     null,   "http://example.com/"],
            ["http://example.com/",           "",     "p",    "http://example.com/"],
            ["http://user:pass@example.com/", "u",    null,   "http://u:pass@example.com/"],
            ["http://user:pass@example.com/", null,   "p",    "http://user:p@example.com/"],
            ["http://user:pass@example.com/", null,   "",     "http://user@example.com/"],
            ["http://example.com/path",       null,   null,   "http://example.com/path"],
            ["http://example.com/PATH",       null,   null,   "http://example.com/PATH"],
            ["http://example.com/path/",      null,   null,   "http://example.com/path/"],
            ["http://example.com/path/.",     null,   null,   "http://example.com/path"],
            ["http://example.com/path/./",    null,   null,   "http://example.com/path/"],
            ["http://example.com/path/..",    null,   null,   "http://example.com/"],
            ["http://example.com/path/../",   null,   null,   "http://example.com/"],
            ["http://example.com/a/b/..",     null,   null,   "http://example.com/a"],
            ["http://example.com/a/b/../",    null,   null,   "http://example.com/a/"],
            ["http://example.com/../",        null,   null,   "http://example.com/"],
            ["http://example.com////",        null,   null,   "http://example.com/"],
            ["http://example.com/a/./b/",     null,   null,   "http://example.com/a/b/"],
            ["http://example.com/a/../b/",    null,   null,   "http://example.com/b/"],
            ["http://example.com/.a/",        null,   null,   "http://example.com/.a/"],
            ["http://example.com/..a/",       null,   null,   "http://example.com/..a/"],
            ["http://日本.example.com/",      null,   null,   "http://日本.example.com/"],
            ["http://EXAMPLE.COM/",           null,   null,   "http://example.com/"],
            ["http://É.example.com/",         null,   null,   "http://é.example.com/"],
            ["http://[::1]/",                 null,   null,   "http://[::1]/"],
            ["http://[0::1]/",                null,   null,   "http://[::1]/"],
            ["http://[Z]/",                   null,   null,   "http://[z]/"],
            ["http://example.com/ ?%61=%3d",  null,   null,   "http://example.com/%20?a=%3D"],
            ["http://example.com/%",          null,   null,   "http://example.com/%25"],
            ["http://example.com/%a",         null,   null,   "http://example.com/%25a"],
            ["http://example.com/%za",        null,   null,   "http://example.com/%25za"],
            ["//EXAMPLE.COM/",                null,   null,   "//example.com/"],
            ["//EXAMPLE.COM/",                "u",    "p",    "//u:p@example.com/"],
            ["/ ",                            null,   null,   "/%20"],
            ["/ ",                            "u",    "p",    "/%20"],
            ["EXAMPLE.COM/",                  null,   null,   "EXAMPLE.COM/"],
            ["EXAMPLE.COM",                   null,   null,   "EXAMPLE.COM"],
            [" ",                             null,   null,   "%20"],            
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
}
