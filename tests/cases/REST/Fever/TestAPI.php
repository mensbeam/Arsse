<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Fever\API;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;
use Phake;

/** @covers \JKingWeb\Arsse\REST\Fever\API<extended> */
class TestAPI extends \JKingWeb\Arsse\Test\AbstractTest {

    protected function v($value) {
        return $value;
    }

    protected function req($dataGet, $dataPost, string $method = "POST", string $type = null, string $url = "", string $user = null): ResponseInterface {
        $url = "/fever/".$url;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
            'HTTP_CONTENT_TYPE' => $type ?? "application/x-www-form-urlencoded",
        ];
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        if (is_array($dataGet)) {
            $req = $req->withRequestTarget($url)->withQueryParams($dataGet);
        } else {
            $req = $req->withRequestTarget($url."?".http_build_query((string) $dataGet, "", "&", \PHP_QUERY_RFC3986));
        }
        if (is_array($dataPost)) {
            $req = $req->withParsedBody($dataPost);
        } else {
            $body = $req->getBody();
            $body->write($strData);
            $req = $req->withBody($body);
        }
        if (isset($user)) {
            if (strlen($user)) {
                $req = $req->withAttribute("authenticated", true)->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        return $this->h->dispatch($req);
    }

    public function setUp() {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->auth->thenReturn(true);
        Arsse::$user->id = "john.doe@example.com";
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->begin->thenReturn(Phake::mock(Transaction::class));
        // instantiate the handler
        $this->h = new API();
    }

    public function tearDown() {
        self::clearData();
    }

    /** @dataProvider provideAuthenticationRequests */
    public function testAuthenticateAUser(bool $httpRequired, bool $tokenEnforced, string $httpUser = null, array $dataPost, array $dataGet, bool $success) {
        self::setConf([
            'userHTTPAuthRequired' => $httpRequired,
            'userSessionEnforced' => $tokenEnforced,
        ], true);
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", "validtoken")->thenReturn(['user' => "jane.doe@example.com"]);
        $exp = new JsonResponse($success ? ['api_version' => API::LEVEL, 'auth' => 1] : ['api_version' => API::LEVEL, 'auth' => 0]);
        $act = $this->req($dataGet, $dataPost, "POST", null, "", $httpUser);
        $this->assertMessage($exp, $act);
    }

    public function provideAuthenticationRequests() {
        return [
            [false, true, null, ['api_key' => "validToken"], ['api' => null], true],
        ];
    }
}
