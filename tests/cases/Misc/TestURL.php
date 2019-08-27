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
    public function testNormalizeAUrl(string $in, string $exp) {
        $this->assertSame($exp, URL::normalize($in));
    }

    public function provideNormalizations() {
        return [
            ["/",                   "/"],
            ["//example.com/",      "//example.com/"],
            ["http://example.com/", "http://example.com/"],
            ["http://[::1]/",       "http://[::1]/"],
            ["HTTP://example.com/", "http://example.com/"],
        ];
    }
}
