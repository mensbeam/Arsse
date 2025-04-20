<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews\PDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("optional")]
#[CoversClass(\JKingWeb\Arsse\REST\NextcloudNews\V1_3::class)]
class TestV1_3 extends \JKingWeb\Arsse\TestCase\REST\NextcloudNews\TestV1_3 {
    use \JKingWeb\Arsse\Test\PDOTest;
}
