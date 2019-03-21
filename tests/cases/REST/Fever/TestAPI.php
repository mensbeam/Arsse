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
use JKingWeb\Arsse\User\Exception as UserException;
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
            $body->write($dataPost);
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
    public function testAuthenticateAUser(bool $httpRequired, bool $tokenEnforced, string $httpUser = null, array $dataPost, array $dataGet, ResponseInterface $exp) {
        self::setConf([
            'userHTTPAuthRequired' => $httpRequired,
            'userSessionEnforced' => $tokenEnforced,
        ], true);
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", "validtoken")->thenReturn(['user' => "jane.doe@example.com"]);
        $act = $this->req($dataGet, $dataPost, "POST", null, "", $httpUser);
        $this->assertMessage($exp, $act);
    }

    public function provideAuthenticationRequests() {
        $success = new JsonResponse(['api_version' => API::LEVEL, 'auth' => 1]);
        $failure = new JsonResponse(['api_version' => API::LEVEL, 'auth' => 0]);
        $denied = new EmptyResponse(401);
        return [
            [false, true,  null, [], ['api' => null], $failure],
            [false, false, null, [], ['api' => null], $failure],
            [true,  true,  null, [], ['api' => null], $denied],
            [true,  false, null, [], ['api' => null], $denied],
            [false, true,  "", [], ['api' => null], $denied],
            [false, false, "", [], ['api' => null], $denied],
            [true,  true,  "", [], ['api' => null], $denied],
            [true,  false, "", [], ['api' => null], $denied],
            [false, true,  null, [], ['api' => null, 'api_key' => "validToken"], $failure],
            [false, false, null, [], ['api' => null, 'api_key' => "validToken"], $failure],
            [true,  true,  null, [], ['api' => null, 'api_key' => "validToken"], $denied],
            [true,  false, null, [], ['api' => null, 'api_key' => "validToken"], $denied],
            [false, true,  null, ['api_key' => "validToken"], ['api' => null], $success],
            [false, false, null, ['api_key' => "validToken"], ['api' => null], $success],
            [true,  true,  null, ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  false, null, ['api_key' => "validToken"], ['api' => null], $denied],
            [false, true,  "", ['api_key' => "validToken"], ['api' => null], $denied],
            [false, false, "", ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  true,  "", ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  false, "", ['api_key' => "validToken"], ['api' => null], $denied],
            [false, true,  "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [false, false, "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [true,  true,  "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [true,  false, "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [false, true,  null, ['api_key' => "invalidToken"], ['api' => null], $failure],
            [false, false, null, ['api_key' => "invalidToken"], ['api' => null], $failure],
            [true,  true,  null, ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  false, null, ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, true,  "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, false, "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  true,  "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  false, "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, true,  "validUser", ['api_key' => "invalidToken"], ['api' => null], $failure],
            [false, false, "validUser", ['api_key' => "invalidToken"], ['api' => null], $success],
            [true,  true,  "validUser", ['api_key' => "invalidToken"], ['api' => null], $failure],
            [true,  false, "validUser", ['api_key' => "invalidToken"], ['api' => null], $success],
        ];
    }

    /** @dataProvider providePasswordCreations */
    public function testRegisterAUserPassword(string $user, string $password = null, $exp) {
        \Phake::when(Arsse::$user)->generatePassword->thenReturn("RANDOM_PASSWORD");
        \Phake::when(Arsse::$db)->tokenCreate->thenReturnCallback(function($user, $class, $id = null) {
            return $id ?? "RANDOM_TOKEN";
        });
        \Phake::when(Arsse::$db)->tokenCreate("john.doe@example.org", $this->anything(), $this->anything())->thenThrow(new UserException("doesNotExist"));
        if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
            $this->assertException($exp);
            API::registerUser($user, $password);
        } else {
            $this->assertSame($exp, API::registerUser($user, $password));
        }
        \Phake::verify(Arsse::$db)->tokenRevoke($user, "fever.login");
        \Phake::verify(Arsse::$db)->tokenCreate($user, "fever.login", md5($user.":".($password ?? "RANDOM_PASSWORD")));
    }

    public function providePasswordCreations() {
        return [
            ["jane.doe@example.com", "secret", "secret"],
            ["jane.doe@example.com", "superman", "superman"],
            ["jane.doe@example.com", null, "RANDOM_PASSWORD"],
            ["john.doe@example.org", null, new UserException("doesNotExist")],
            ["john.doe@example.net", null, "RANDOM_PASSWORD"],
            ["john.doe@example.net", "secret", "secret"],
        ];
    }

    public function testUnregisterAUser() {
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(3);
        $this->assertTrue(API::unregisterUser("jane.doe@example.com"));
        \Phake::verify(Arsse::$db)->tokenRevoke("jane.doe@example.com", "fever.login");
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(0);
        $this->assertFalse(API::unregisterUser("john.doe@example.com"));
        \Phake::verify(Arsse::$db)->tokenRevoke("john.doe@example.com", "fever.login");
    }
}
