<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Rule\Rule;

/** @covers \JKingWeb\Arsse\Rule\Rule */
class TestRule extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testPrepareAPattern(): void {
        $exp = "`\\`..\\`..\\`..\\\\\\`..`u";
        $this->assertSame($exp, Rule::prep("`..`..\\`..\\\\`.."));
    }

    public function testPrepareAnInvalidPattern(): void {
        $this->assertException("invalidPattern", "Rule");
        Rule::prep("[");
    }
}