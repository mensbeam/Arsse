<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\Miniflux\V1;
use JKingWeb\Arsse\REST\Miniflux\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse as Response;
use Laminas\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\Miniflux\V1<extended> */
class TestV1 extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $transaction;
    protected $token = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";

    protected function req(string $method, string $target, $data = "", array $headers = [], bool $authenticated = true, bool $body = true): ResponseInterface {
        $prefix = "/v1";
        $url = $prefix.$target;
        if ($body) {
            $params = [];
        } else {
            $params = $data;
            $data = [];
        }
        $req = $this->serverRequest($method, $url, $prefix, $headers, [], $data, "application/json", $params, $authenticated ? "john.doe@example.com" : "");
        return $this->h->dispatch($req);
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        Arsse::$user->id = "john.doe@example.com";
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        $this->transaction = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($this->transaction);
        //initialize a handler
        $this->h = new V1();
    }

    public function tearDown(): void {
        self::clearData();
    }

    protected function v($value) {
        return $value;
    }

    /** @dataProvider provideAuthResponses */
    public function testAuthenticateAUser($token, bool $auth, bool $success): void {
        $exp = $success ? new EmptyResponse(404) : new ErrorResponse("401", 401);
        $user = "john.doe@example.com";
        if ($token !== null) {
            $headers = ['X-Auth-Token' => $token];
        } else {
            $headers = [];
        }
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("miniflux.login", $this->token)->thenReturn(['user' => $user]);
        $this->assertMessage($exp, $this->req("GET", "/", "", $headers, $auth));
        $this->assertSame($success ? $user : null, Arsse::$user->id);
    }

    public function provideAuthResponses(): iterable {
        return [
            [null,                     false, false],
            [null,                     true,  true],
            [$this->token,             false, true],
            [[$this->token, "BOGUS"],  false, true],
            ["",                       true,  true],
            [["", "BOGUS"],            true,  true],
            ["NOT A TOKEN",            false, false],
            ["NOT A TOKEN",            true,  false],
            [["BOGUS", $this->token],  false, false],
            [["", $this->token],       false, false],
        ];
    }

    /** @dataProvider provideInvalidPaths */
    public function testRespondToInvalidPaths($path, $method, $code, $allow = null): void {
        $exp = new EmptyResponse($code, $allow ? ['Allow' => $allow] : []);
        $this->assertMessage($exp, $this->req($method, $path));
    }

    public function provideInvalidPaths(): array {
        return [
            ["/",                  "GET",     404],
            ["/me",                "POST",    405, "GET"],
        ];
    }

    public function xtestRespondToInvalidInputTypes(): void {
        $exp = new EmptyResponse(415, ['Accept' => "application/json"]);
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => "application/xml"]));
        $exp = new EmptyResponse(400);
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>'));
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => null]));
    }

    /** @dataProvider provideOptionsRequests */
    public function xtestRespondToOptionsRequests(string $url, string $allow, string $accept): void {
        $exp = new EmptyResponse(204, [
            'Allow'  => $allow,
            'Accept' => $accept,
        ]);
        $this->assertMessage($exp, $this->req("OPTIONS", $url));
    }

    public function provideOptionsRequests(): array {
        return [
            ["/feeds",      "HEAD,GET,POST", "application/json"],
            ["/feeds/2112", "DELETE",        "application/json"],
            ["/user",       "HEAD,GET",      "application/json"],
        ];
    }
}
