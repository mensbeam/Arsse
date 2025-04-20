<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Rule\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\Rule\Rule::class)]
class TestRule extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testPrepareAPattern(): void {
        $exp = "`\\`..\\`..\\`..\\\\\\`..`u";
        $this->assertTrue(Rule::validate("`..`..\\`..\\\\`.."));
        $this->assertSame($exp, Rule::prep("`..`..\\`..\\\\`.."));
    }

    public function testPrepareAnInvalidPattern(): void {
        $this->assertFalse(Rule::validate("["));
        $this->assertException("invalidPattern", "Rule");
        Rule::prep("[");
    }

    public function testPrepareAnEmptyPattern(): void {
        $this->assertTrue(Rule::validate(""));
        $this->assertSame("", Rule::prep(""));
    }


    #[DataProvider('provideApplications')]
    public function testApplyRules(string $keepRule, string $blockRule, string $url, string $title, string $author, array $categories, $exp): void {
        $keepRule = Rule::prep($keepRule);
        $blockRule = Rule::prep($blockRule);
        $this->assertSame($exp, Rule::apply($keepRule, $blockRule, $url, $title, $author, $categories));
    }

    public static function provideApplications(): iterable {
        return [
            ["",           "",           "",     "Title",   "",                    ["Dummy", "Category"], true],
            ["^Title$",    "",           "",     "Title",   "",                    ["Dummy", "Category"], true],
            ["^Category$", "",           "",     "Title",   "",                    ["Dummy", "Category"], true],
            ["^Naught$",   "",           "",     "Title",   "",                    ["Dummy", "Category"], false],
            ["",           "^Title$",    "",     "Title",   "",                    ["Dummy", "Category"], false],
            ["",           "^Category$", "",     "Title",   "",                    ["Dummy", "Category"], false],
            ["",           "^Naught$",   "",     "Title",   "",                    ["Dummy", "Category"], true],
            ["^Category$", "^Category$", "",     "Title",   "",                    ["Dummy", "Category"], false],
            ["",           "^A B C$",    "",     "A  B\nC", "",                    ["X\n   Y  \t  \r Z"], false],
            ["",           "^X Y Z$",    "",     "A  B\nC", "",                    ["X\n   Y  \t  \r Z"], false],
            ["sample",     "",           "",     "Title",   "http://example.com/", ["Dummy", "Category"], false],
            ["example",    "",           "",     "Title",   "http://example.com/", ["Dummy", "Category"], true],
            ["example",    "example",    "",     "Title",   "http://example.com/", ["Dummy", "Category"], false],
            ["Jane",       "",           "John", "Title",   "",                    ["Dummy", "Category"], false],
            ["John",       "",           "John", "Title",   "",                    ["Dummy", "Category"], true],
            ["John",       "John",       "John", "Title",   "",                    ["Dummy", "Category"], false],
        ];
    }
}
