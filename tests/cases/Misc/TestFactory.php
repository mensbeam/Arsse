<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Factory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Factory::class)]
class TestFactory extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testInstantiateAClass(): void {
        $f = new Factory;
        $this->assertInstanceOf(\stdClass::class, $f->get(\stdClass::class));
    }
}
