<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Miniflux\Status;
use JKingWeb\Arsse\REST\Miniflux\V1;
use Psr\Http\Message\ResponseInterface;

/** @covers \JKingWeb\Arsse\REST\Miniflux\Status */
class TestStatus extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideRequests */
    public function testAnswerStatusRequests(string $url, string $method, ResponseInterface $exp): void {
        $act = (new Status)->dispatch($this->serverRequest($method, $url, ""));
        $this->assertMessage($exp, $act);
    }

    public static function provideRequests(): iterable {
        return [
            ["/version",     "GET",     HTTP::respText(V1::VERSION)],
            ["/version",     "POST",    HTTP::respEmpty(405, ['Allow' => "HEAD, GET"])],
            ["/version",     "OPTIONS", HTTP::respEmpty(204, ['Allow' => "HEAD, GET"])],
            ["/healthcheck", "GET",     HTTP::respText("OK")],
            ["/healthcheck", "POST",    HTTP::respEmpty(405, ['Allow' => "HEAD, GET"])],
            ["/healthcheck", "OPTIONS", HTTP::respEmpty(204, ['Allow' => "HEAD, GET"])],
            ["/version/",     "GET",    HTTP::respEmpty(404)],
        ];
    }
}
