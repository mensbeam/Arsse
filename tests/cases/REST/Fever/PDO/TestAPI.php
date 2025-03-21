<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Fever\PDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("optional")]
#[CoversClass(\JKingWeb\Arsse\REST\Fever\API::class)]
class TestAPI extends \JKingWeb\Arsse\TestCase\REST\Fever\TestAPI {
    use \JKingWeb\Arsse\Test\PDOTest;
}
