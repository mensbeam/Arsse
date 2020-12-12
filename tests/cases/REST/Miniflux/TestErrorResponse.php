<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\REST\Miniflux\ErrorResponse;

/** @covers \JKingWeb\Arsse\REST\Miniflux\ErrorResponse */
class TestErrorResponse extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testCreateConstantResponse(): void {
        $act = new ErrorResponse("401", 401);
        $this->assertSame('{"error_message":"Access Unauthorized"}', (string) $act->getBody());
    }

    public function testCreateVariableResponse(): void {
        $act = new ErrorResponse(["InvalidBodyJSON", "Doh!"], 401);
        $this->assertSame('{"error_message":"Invalid JSON payload: Doh!"}', (string) $act->getBody());
    }
}
