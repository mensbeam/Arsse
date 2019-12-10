<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Microsub;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\Microsub\Auth;
use JKingWeb\Arsse\REST\Microsub\ExceptionAuth;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;

/** @covers \JKingWeb\Arsse\REST\Microsub\Auth<extended> */
class TestAuth extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        Arsse::$db = \Phake::mock(Database::class);
    }

    public function req(string $url, string $method = "GET", array $params = [], array $headers = [], array $data = [], string $type = "application/x-www-form-urlencoded", string $body = null, string $user = null): ResponseInterface {
        $type = (strtoupper($method) === "GET") ? "" : $type;
        $req = $this->serverRequest($method, $url, "/u/", $headers, [], $body ?? $data, $type, $params, $user);
        return (new \JKingWeb\Arsse\REST\Microsub\Auth)->dispatch($req);
    }

    /** @dataProvider provideInvalidRequests */
    public function testHandleInvalidRequests(ResponseInterface $exp, string $method, string $url, string $type = null) {
        $act = $this->req("http://example.com".$url, $method, [], [], [], $type ?? "");
        $this->assertMessage($exp, $act);
    }

    public function provideInvalidRequests() {
        $r404 = new EmptyResponse(404);
        $r405g = new EmptyResponse(405, ['Allow' => "GET"]);
        $r405gp = new EmptyResponse(405, ['Allow' => "GET,POST"]);
        $r415 = new EmptyResponse(415, ['Accept' => "application/x-www-form-urlencoded"]);
        return [
            [$r404,   "GET",  "/u/"],
            [$r404,   "GET",  "/u/john.doe/hello"],
            [$r404,   "GET",  "/u/john.doe/"],
            [$r404,   "GET",  "/u/john.doe?f=hello"],
            [$r404,   "GET",  "/u/?f="],
            [$r404,   "GET",  "/u/?f=goodbye"],
            [$r405g,  "POST", "/u/john.doe"],
            [$r405gp, "PUT",  "/u/?f=token"],
            [$r404,   "POST", "/u/john.doe?f=token"],
            [$r415,   "POST", "/u/?f=token", "application/json"],
        ];
    }

    /** @dataProvider provideOptionsRequests */
    public function testHandleOptionsRequests(string $url, array $headerFields) {
        $exp = new EmptyResponse(204, $headerFields);
        $this->assertMessage($exp, $this->req("http://example.com".$url, "OPTIONS"));
    }

    public function provideOptionsRequests() {
        $ident = ['Allow' => "GET"];
        $other = ['Allow' => "GET,POST", 'Accept' => "application/x-www-form-urlencoded"];
        return [
            ["/u/john.doe", $ident],
            ["/u/?f=token", $other],
            ["/u/?f=auth",  $other],
        ];
    }

    /** @dataProvider provideDiscoveryRequests */
    public function testDiscoverAUser(string $url, string $origin) {
        $auth = $origin."/u/?f=auth";
        $token = $origin."/u/?f=token";
        $microsub = $origin."/microsub";
        $exp = new HtmlResponse('<meta charset="UTF-8"><link rel="authorization_endpoint" href="'.htmlspecialchars($auth).'"><link rel="token_endpoint" href="'.htmlspecialchars($token).'"><link rel="microsub" href="'.htmlspecialchars($microsub).'">', 200, ['Link' => [
            "<$auth>; rel=\"authorization_endpoint\"",
            "<$token>; rel=\"token_endpoint\"",
            "<$microsub>; rel=\"microsub\"",
        ]]);
        $this->assertMessage($exp, $this->req($url));
    }

    public function provideDiscoveryRequests() {
        return [
            ["http://example.com/u/john.doe",      "http://example.com"],
            ["http://example.com:80/u/john.doe",   "http://example.com"],
            ["https://example.com/u/john.doe",     "https://example.com"],
            ["https://example.com:443/u/john.doe", "https://example.com"],
            ["http://example.com:443/u/john.doe",  "http://example.com:443"],
            ["https://example.com:80/u/john.doe",  "https://example.com:80"],
        ];
    }

    /** @dataProvider provideLoginData */
    public function testLogInAUser(array $params, string $authenticatedUser = null, ResponseInterface $exp) {
        \Phake::when(Arsse::$db)->tokenCreate->thenReturn("authCode");
        $act = $this->req("http://example.com/u/?f=auth", "GET", $params, [], [], "", null, $authenticatedUser);
        $this->assertMessage($exp, $act);
        if ($act->getStatusCode() == 302 && !preg_match("/\berror=\w/", $act->getHeaderLine("Location") ?? "")) {
            \Phake::verify(Arsse::$db)->tokenCreate($authenticatedUser, "microsub.auth", null, $this->isInstanceOf(\DateTimeInterface::class), json_encode([
                'me'            => $params['me'],
                'client_id'     => $params['client_id'],
                'redirect_uri'  => $params['redirect_uri'],
                'response_type' => strlen($params['response_type'] ?? "") ? $params['response_type'] : "id",
            ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        } else {
            \Phake::verify(Arsse::$db, \Phake::times(0))->tokenCreate;
        }
    }

    public function provideLoginData() {
        return [
            'Challenge'         => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], null,       new EmptyResponse(401)],
            'Failed challenge'  => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "",         new EmptyResponse(401)],
            'Wrong user 1'      => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "jane.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong user 2'      => [['me' => "https://example.com/u/jane.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong domain 1'    => [['me' => "https://example.net/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong domain 2'    => [['me' => "https:///u/john.doe",               'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong port'        => [['me' => "https://example.com:80/u/john.doe", 'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong scheme'      => [['me' => "ftp://example.com/u/john.doe",      'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Wrong path'        => [['me' => "http://example.com/user/john.doe",  'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=access_denied"])],
            'Bad redirect 1'    => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "//example.org/redirect",         'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(400)],
            'Bad redirect 2'    => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "https:///redirect",              'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(400)],
            'Bad response type' => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "bad"],  "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&error=unsupported_response_type"])],
            'Success 1'         => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=ABCDEF&code=authCode"])],
            'Success 2'         => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/redirect",    'state' => "R&R",    'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/redirect?state=R%26R&code=authCode"])],
            'Success 3'         => [['me' => "https://example.com/u/john.doe",    'client_id' => "http://example.org/", 'redirect_uri' => "http://example.org/?p=redirect", 'state' => "ABCDEF", 'response_type' => "code"], "john.doe", new EmptyResponse(302, ['Location' => "http://example.org/?p=redirect&state=ABCDEF&code=authCode"])],
        ];
    }

    /** @dataProvider provideAuthData */
    public function testVerifyAnAuthenticationCode(array $params, string $user, $data, ResponseInterface $exp) {
        if ($data instanceof \Exception) {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.auth", $params['code'] ?? "")->thenThrow($data);
        } else {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.auth", $params['code'] ?? "")->thenReturn(['user' => $user, 'data' => $data]);
        }
        $act = $this->req("http://example.com/u/?f=auth", "POST", [], [], $params);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db, \Phake::times($act->getStatusCode() == 200 ? 1 : 0))->tokenRevoke($user, "microsub.auth", $params['code'] ?? "");
    }

    public function provideAuthData() {
        return [
            'Missing code 1' => [[                        'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Missing code 2' => [['code' => "",           'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Missing URL 1'  => [['code' => "code",                                                 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Missing URL 2'  => [['code' => "code",       'redirect_uri' => "",                     'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Missing ID 1'   => [['code' => "code",       'redirect_uri' => "https://example.org/",                                      ], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Missing ID 2'   => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => ""                    ], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_request"], 400)],
            'Mismatched URL' => [['code' => "code",       'redirect_uri' => "https://example.net/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_client" ], 400)],
            'Mismatched ID'  => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.org/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['error' => "invalid_client" ], 400)],
            'Bad data 1'     => [['code' => "bad-data1",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", null,                                                                                                 new Response(['error' => "invalid_grant"  ], 400)],
            'Bad data 2'     => [['code' => "bad-data2",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{client_id":"https://example.net/"}',                                                                new Response(['error' => "invalid_grant"  ], 400)],
            'Bad data 3'     => [['code' => "bad-data3",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/"}',                                                            new Response(['error' => "invalid_grant"  ], 400)],
            'Bad user'       => [['code' => "bad-user",   'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", new ExceptionInput("subjectMissing"),                                                                 new Response(['error' => "invalid_grant"  ], 400)],
            'Bad type'       => [['code' => "bad-type",   'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"token"}', new Response(['error' => "invalid_grant"  ], 400)],
            'Success 1'      => [['code' => "valid-code", 'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "someone", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/"}',                         new Response(['me' => "http://example.com/u/someone"], 200)],
            'Success 2'      => [['code' => "good-code",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/"], "somehow", '{"redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"id"}',    new Response(['me' => "http://example.com/u/somehow"], 200)],
        ];
    }

    /** @dataProvider provideTokenRequests */
    public function testIssueAnAccessToken(array $params, string $user, $data, ResponseInterface $exp) {
        if ($data instanceof \Exception) {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.auth", $params['code'] ?? "")->thenThrow($data);
        } else {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.auth", $params['code'] ?? "")->thenReturn(['user' => $user, 'data' => $data]);
        }
        \Phake::when(Arsse::$db)->tokenCreate->thenReturn("TOKEN");
        $act = $this->req("http://example.com/u/?f=token", "POST", [], [], $params);
        $this->assertMessage($exp, $act);
        if ($act->getStatusCode() == 200) {
            $input = '{"me":"'.($params['me'] ?? "").'","client_id":"'.($params['client_id'] ?? "").'"}';
            \Phake::verify(Arsse::$db, \Phake::times(1))->tokenCreate($user, "microsub.access", null, null, $input);
            \Phake::verify(Arsse::$db, \Phake::times(1))->tokenRevoke($user, "microsub.auth", $params['code'] ?? "");
        } else {
            \Phake::verify(Arsse::$db, \Phake::times(0))->tokenCreate;
            \Phake::verify(Arsse::$db, \Phake::times(0))->tokenRevoke;
        }
    }

    public function provideTokenRequests() {
        $scopes = implode(" ", Auth::SCOPES);
        return [
            'Missing code 1'   => [[                        'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing code 2'   => [['code' => "",           'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing URL 1'    => [['code' => "code",                                                 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing URL 2'    => [['code' => "code",       'redirect_uri' => "",                     'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing ID 1'     => [['code' => "code",       'redirect_uri' => "https://example.org/",                                        'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing ID 2'     => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "",                     'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing grant 1'  => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "",                   'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "unsupported_grant_type"], 400)],
            'Missing grant 2'  => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/",                                       'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "unsupported_grant_type"], 400)],
            'Missing me 1'     => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => ""                             ], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Missing me 2'     => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code",                                        ], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_request"       ], 400)],
            'Mismatched URL'   => [['code' => "code",       'redirect_uri' => "https://example.net/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_client"        ], 400)],
            'Mismatched ID'    => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.org/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_client"        ], 400)],
            'Mismatched grant' => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "mismatch",           'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "unsupported_grant_type"], 400)],
            'Mismatched me'    => [['code' => "code",       'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/"       ], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 1'       => [['code' => "bad-data1",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", null,                                                                                                                                     new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 2'       => [['code' => "bad-data2",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{                                     "redirect_uri":"https://example.org/", client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 3'       => [['code' => "bad-data3",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone",                                       client_id":"https://example.net/","response_type":"code"}', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 4'       => [['code' => "bad-data4",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/",                                   "response_type":"code"}', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 5'       => [['code' => "bad-data5",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/",                      }', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad data 6'       => [['code' => "bad-data6",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"id"  }', new Response(['error' => "invalid_grant"         ], 400)],
            'Bad user'         => [['code' => "bad-user",   'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", new ExceptionInput("subjectMissing"),                                                                                                     new Response(['error' => "invalid_grant"         ], 400)],
            'Success 1'        => [['code' => "valid-code", 'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/someone"], "someone", '{"me":"https://example.com/u/someone","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['me' => "http://example.com/u/someone", 'token_type' => "Bearer", 'access_token' => "TOKEN", 'scope' => $scopes], 200)],
            'Success 2'        => [['code' => "good-code",  'redirect_uri' => "https://example.org/", 'client_id' => "https://example.net/", 'grant_type' => "authorization_code", 'me' => "https://example.com/u/somehow"], "somehow", '{"me":"https://example.com/u/somehow","redirect_uri":"https://example.org/","client_id":"https://example.net/","response_type":"code"}', new Response(['me' => "http://example.com/u/somehow", 'token_type' => "Bearer", 'access_token' => "TOKEN", 'scope' => $scopes], 200)],
        ];
    }

    /** @dataProvider provideBearers */
    public function testLogInABearer(string $authorization, array $scopes, string $token, string $user, $data, $exp) {
        if ($data instanceof \Exception) {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.access", $this->anything())->thenThrow($data);
        } else {
            \Phake::when(Arsse::$db)->tokenLookup("microsub.access", $this->anything())->thenReturn(['user' => $user, 'data' => $data]);
        }
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
        }
        try {
            $act = Auth::validateBearer($authorization, $scopes);
            $this->assertSame($exp, $act);
        } finally {
            if (strlen($token)) {
                \Phake::verify(Arsse::$db)->tokenLookup("microsub.access", $token);
            } else {
                \Phake::verify(Arsse::$db, \Phake::times(0))->tokenLookup;
            }
        }
    }

    public function provideBearers() {
        return [
            'Not a bearer'         => ["Beaver TOKEN", [],           "",      "",        "",                                   new ExceptionAuth("invalid_request")],
            'Token missing 1'      => ["Bearer",       [],           "",      "",        "",                                   new ExceptionAuth("invalid_request")],
            'Token missing 2'      => ["Bearer ",      [],           "",      "",        "",                                   new ExceptionAuth("invalid_request")],
            'Not a token'          => ["Bearer !",     [],           "",      "",        "",                                   new ExceptionAuth("invalid_request")],
            'Invalid token'        => ["Bearer TOKEN", [],           "TOKEN", "",        new ExceptionInput("subjectMissing"), new ExceptionAuth("invalid_token")],
            'Insufficient scope 1' => ["Bearer TOKEN", ["missing"],  "TOKEN", "someone", null,                                 new ExceptionAuth("insufficient_scope")],
            'Insufficient scope 2' => ["Bearer TOKEN", ["channels"], "TOKEN", "someone", '{"scope":["read","follow"]}',        new ExceptionAuth("insufficient_scope")],
            'Success 1'            => ["Bearer TOKEN", [],           "TOKEN", "someone", null,                                 ["someone", ['scope' => Auth::SCOPES]]],
            'Success 2'            => ["bearer TOKEN", [],           "TOKEN", "someone", null,                                 ["someone", ['scope' => Auth::SCOPES]]],
            'Success 3'            => ["BEARER TOKEN", [],           "TOKEN", "someone", null,                                 ["someone", ['scope' => Auth::SCOPES]]],
            'Broken data'          => ["Bearer TOKEN", [],           "TOKEN", "someone", '{',                                  ["someone", ['scope' => Auth::SCOPES]]],
        ];
    }
}
