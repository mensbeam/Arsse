<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\REST\Miniflux\Status;
use JKingWeb\Arsse\REST\Miniflux\V1;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;

/** @covers \JKingWeb\Arsse\REST\Miniflux\Status */
class TestStatus extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideRequests */
    public function testAnswerStatusRequests(string $url, string $method, ResponseInterface $exp): void {
        $act = (new Status)->dispatch($this->serverRequest($method, $url, ""));
        $this->assertMessage($exp, $act);
    }

    public function provideRequests(): iterable {
        return [
            ["/version",     "GET",     new TextResponse(V1::VERSION)],
            ["/version",     "POST",    new EmptyResponse(405, ['Allow' => "HEAD, GET"])],
            ["/version",     "OPTIONS", new EmptyResponse(204, ['Allow' => "HEAD, GET"])],
            ["/healthcheck", "GET",     new TextResponse("OK")],
            ["/healthcheck", "POST",    new EmptyResponse(405, ['Allow' => "HEAD, GET"])],
            ["/healthcheck", "OPTIONS", new EmptyResponse(204, ['Allow' => "HEAD, GET"])],
            ["/version/",     "GET",    new EmptyResponse(404)],
        ];
    }
}
