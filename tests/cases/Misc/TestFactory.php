<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Factory;

/** @covers \JKingWeb\Arsse\Factory */
class TestFactory extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testInstantiateAClass(): void {
        $f = new Factory;
        $this->assertInstanceOf(\stdClass::class, $f->get(\stdClass::class));
    }
}
