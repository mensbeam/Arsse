<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\HTTP;

/** @covers \JKingWeb\Arsse\Misc\HTTP */
class TestHTTP extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideMediaTypes */
    public function testMatchMediaType(string $header, array $types, bool $exp): void {
        $msg = (new \Laminas\Diactoros\Request)->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, ...$types));
        $msg = (new \Laminas\Diactoros\Response)->withHeader("Content-Type", $header);
        $this->assertSame($exp, HTTP::matchType($msg, ...$types));
    }

    public function provideMediaTypes(): array {
        return [
            ["application/json",         ["application/json"],              true],
            ["APPLICATION/JSON",         ["application/json"],              true],
            ["text/JSON",                ["application/json", "text/json"], true],
            ["text/json; charset=utf-8", ["application/json", "text/json"], true],
            ["",                         ["application/json"],              false],
            ["",                         ["application/json", ""],          true],
            ["application/json ;",       ["application/json"],              true],
        ];
    }
}
