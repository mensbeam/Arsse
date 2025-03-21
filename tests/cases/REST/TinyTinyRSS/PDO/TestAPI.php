<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS\PDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("optional")]
#[CoversClass(\JKingWeb\Arsse\REST\TinyTinyRSS\API::class)]
#[CoversClass(\JKingWeb\Arsse\REST\TinyTinyRSS\Exception::class)]
class TestAPI extends \JKingWeb\Arsse\TestCase\REST\TinyTinyRSS\TestAPI {
    use \JKingWeb\Arsse\Test\PDOTest;
}
