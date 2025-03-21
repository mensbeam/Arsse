<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Miniflux\PDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("optional")]
#[CoversClass(\JKingWeb\Arsse\REST\Miniflux\Token::class)]
class TestToken extends \JKingWeb\Arsse\TestCase\REST\Miniflux\TestV1 {
    use \JKingWeb\Arsse\Test\PDOTest;
}
