<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST;

use JKingWeb\Arsse\REST\Target;

/** @covers \JKingWeb\Arsse\REST\Target */
class TestTarget extends \JKingWeb\Arsse\Test\AbstractTest {

    /** @dataProvider provideTargetUrls */
    public function testParseTargetUrl(string $target, array $path, bool $relative, bool $index, string $query, string $fragment, string $normalized) {
        $test = new Target($target);
        $this->assertEquals($path, $test->path, "Path does not match");
        $this->assertSame($path, $test->path, "Path does not match exactly");
        $this->assertSame($relative, $test->relative, "Relative flag does not match");
        $this->assertSame($index, $test->index, "Index flag does not match");
        $this->assertSame($query, $test->query, "Query does not match");
        $this->assertSame($fragment, $test->fragment, "Fragment does not match");
    }

    /** @dataProvider provideTargetUrls */
    public function testNormalizeTargetUrl(string $target, array $path, bool $relative, bool $index, string $query, string $fragment, string $normalized) {
        $test = new Target("");
        $test->path = $path;
        $test->relative = $relative;
        $test->index = $index;
        $test->query = $query;
        $test->fragment = $fragment;
        $this->assertSame($normalized, (string) $test);
        $this->assertSame($normalized, Target::normalize($target));
    }

    public function provideTargetUrls() {
        return [
            ["/",                      [],                false, true,  "",    "",    "/"],
            ["",                       [],                true,  true,  "",    "",    ""],
            ["/index.php",             ["index.php"],     false, false, "",    "",    "/index.php"],
            ["index.php",              ["index.php"],     true,  false, "",    "",    "index.php"],
            ["/ook/",                  ["ook"],           false, true,  "",    "",    "/ook/"],
            ["ook/",                   ["ook"],           true,  true,  "",    "",    "ook/"],
            ["/eek/../ook/",           ["ook"],           false, true,  "",    "",    "/ook/"],
            ["eek/../ook/",            ["ook"],           true,  true,  "",    "",    "ook/"],
            ["/./ook/",                ["ook"],           false, true,  "",    "",    "/ook/"],
            ["./ook/",                 ["ook"],           true,  true,  "",    "",    "ook/"],
            ["/../ook/",               [null,"ook"],      false, true,  "",    "",    "/../ook/"],
            ["../ook/",                [null,"ook"],      true,  true,  "",    "",    "../ook/"],
            ["0",                      ["0"],             true,  false, "",    "",    "0"],
            ["%6f%6F%6b",              ["ook"],           true,  false, "",    "",    "ook"],
            ["%2e%2E%2f%2E%2Fook%2f",  [".././ook/"],     true,  false, "",    "",    "..%2F.%2Fook%2F"],
            ["%2e%2E/%2E/ook%2f",      ["..",".","ook/"], true,  false, "",    "",    "%2E%2E/%2E/ook%2F"],
            ["...",                    ["..."],           true,  false, "",    "",    "..."],
            ["%2e%2e%2e",              ["..."],           true,  false, "",    "",    "..."],
            ["/?",                     [],                false, true,  "",    "",    "/"],
            ["/#",                     [],                false, true,  "",    "",    "/"],
            ["/?#",                    [],                false, true,  "",    "",    "/"],
            ["#%2e",                   [],                true,  true,  "",    ".",   "#."],
            ["?%2e",                   [],                true,  true,  "%2e", "",    "?%2e"],
            ["?%2e#%2f",               [],                true,  true,  "%2e", "/",   "?%2e#%2F"],
            ["#%2e?%2f",               [],                true,  true,  "",    ".?/", "#.%3F%2F"],
        ];
    }
}
