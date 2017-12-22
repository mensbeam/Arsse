<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\User;

use JKingWeb\Arsse\Arsse;

/** @covers \JKingWeb\Arsse\User */
class TestMockInternal extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\Test\User\CommonTests;

    const USER1 = "john.doe@example.com";
    const USER2 = "jane.doe@example.com";

    public $drv = \JKingWeb\Arsse\Test\User\DriverInternalMock::class;

    public function setUpSeries() {
        Arsse::$db = null;
    }
}
