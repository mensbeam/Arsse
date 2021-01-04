<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Rule\Rule;
use JKingWeb\Arsse\Rule\Exception;

/** @covers \JKingWeb\Arsse\Rule\Rule */
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

    /** @dataProvider provideApplications */
    public function testApplyRules(string $keepRule, string $blockRule, string $title, array $categories, $exp): void {
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
            Rule::apply($keepRule, $blockRule, $title, $categories);
        } else {
            $this->assertSame($exp, Rule::apply($keepRule, $blockRule, $title, $categories));
        }
    }

    public function provideApplications(): iterable {
        return [
            ["",           "",           "Title", ["Dummy", "Category"], true],
            ["^Title$",    "",           "Title", ["Dummy", "Category"], true],
            ["^Category$", "",           "Title", ["Dummy", "Category"], true],
            ["^Naught$",   "",           "Title", ["Dummy", "Category"], false],
            ["",           "^Title$",    "Title", ["Dummy", "Category"], false],
            ["",           "^Category$", "Title", ["Dummy", "Category"], false],
            ["",           "^Naught$",   "Title", ["Dummy", "Category"], true],
            ["^Category$", "^Category$", "Title", ["Dummy", "Category"], false],
            ["[",          "",           "Title", ["Dummy", "Category"], true],
            ["",           "[",          "Title", ["Dummy", "Category"], true],
        ];
    }
}
