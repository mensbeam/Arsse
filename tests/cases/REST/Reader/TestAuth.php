<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Auth::class)]
class TestAuth extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-21T23:09:17.189065Z";
    protected $h = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create mock timestamps
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth(\Phake::anyParameters())->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin(\Phake::anyParameters())->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenCreate(\Phake::anyParameters())->thenReturn("12345");
        $this->h = new Auth;
    }

    #[DataProvider("provideAuthentications")]
    public function testAuthenticateAUser(string $method, string $target, string $post, ?array $cred, bool $out, ResponseInterface $exp): void {
        \Phake::when(Arsse::$user)->auth(\Phake::anyParameters())->thenReturn($out);
        $r = $this->serverRequest($method, "/api/greader.php/accounts/".$target, "/api/greader.php/accounts/ClientLogin", [], [], $post, "application/x-www-form-urlencoded", [], null);
        $act = $this->h->dispatch($r);
        $this->assertMessage($exp, $act);
        if (is_array($cred)) {
            \Phake::verify(Arsse::$user)->auth(...$cred);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->auth(\Phake::anyParameters());
        }
        if (is_array($cred) && $out) {
            \Phake::verify(Arsse::$db)->tokenCreate($cred[0], "reader.login", null, Date::normalize(self::NOW)->add(new \DateInterval("P7D")));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->tokenCreate(\Phake::anyParameters());
        }
    }

    public static function provideAuthentications(): iterable {
        $token = "12345";
        return [
            ["GET",  "ClientLogin?Email=ook&Passwd=eek", "",                     ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin",                      "Email=ook&Passwd=eek", ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin?Email=ook",            "Passwd=eek",           ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin?Passwd=eek",           "Email=ook",            ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin?Email=a%20b",          "Passwd=c%40d",         ["a b", "c@d"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin?Email=ook&Passwd=eek", "",                     ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["GET",  "ClientLogin?Email=ook&Passwd=eek", "",                     ["ook", "eek"], false, HTTP::respText("Error=BadAuthentication", 400)],
            ["GET",  "ClientLogin/bad",                  "Email=ook&Passwd=eek", null,           false, HTTP::respEmpty(404)],
            ["POST", "ClientLogin",                      "Email=ook&Passwd=eek", ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["POST", "ClientLogin?Email=ook",            "Passwd=eek",           ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["POST", "ClientLogin?Passwd=eek",           "Email=ook",            ["ook", "eek"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["POST", "ClientLogin?Email=a%20b",          "Passwd=c%40d",         ["a b", "c@d"], true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            ["POST", "ClientLogin?Email=ook&Passwd=eek", "",                     ["ook", "eek"], false, HTTP::respText("Error=BadAuthentication", 400)],
            ["POST", "ClientLogin/bad",                  "Email=ook&Passwd=eek", null,           false, HTTP::respEmpty(404)],
            ["PUT",  "ClientLogin",                      "Email=ook&Passwd=eek", null,           false, HTTP::respEmpty(405)],
        ];
    }

    #[DataProvider("provideBasicAuthentications")]
    public function testExerciseBasicAuthentication(bool $httpReq, bool $authTried, bool $authPassed, ResponseInterface $exp): void {
        Arsse::$conf->userHTTPAuthRequired = $httpReq;
        $user = $authTried ? ($authPassed ? "ook" : "") : null;
        $r = $this->serverRequest("GET", "/api/greader.php/accounts/ClientLogin", "/api/greader.php/accounts/ClientLogin", [], [], "Email=ook&Passwd=eek", "application/x-www-form-urlencoded", [], $user);
        $act = $this->h->dispatch($r);
        $this->assertMessage($exp, $act);
    }

    public static function provideBasicAuthentications(): iterable {
        $token = "12345";
        return [
            [false, false, false, HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            [false, true,  false, HTTP::respEmpty(401)],
            [false, true,  true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
            [true,  false, false, HTTP::respEmpty(401)],
            [true,  true,  false, HTTP::respEmpty(401)],
            [true,  true,  true,  HTTP::respText("SID=$token\nLSID=$token\nAuth=$token\nexpires_in=604800\n")],
        ];
    }

    public function testAuthenticateForm(): void {
        // The following mimicks actual input from the Readrops Android client
        $body = "--92dfe96c-c86d-48c6-92a2-fb3b2bcb5fe9\r\nContent-Disposition: form-data; name=\"Email\"\r\nContent-Length: 20\r\n\r\njohn.doe@example.com\r\n--92dfe96c-c86d-48c6-92a2-fb3b2bcb5fe9\r\nContent-Disposition: form-data; name=\"Passwd\"\r\nContent-Length: 6\r\n\r\nsecret\r\n--92dfe96c-c86d-48c6-92a2-fb3b2bcb5fe9--";
        $r = $this->serverRequest("POST", "/api/greader.php/accounts/ClientLogin", "/api/greader.php/accounts/ClientLogin", [], [], $body, "multipart/form-data; boundary=92dfe96c-c86d-48c6-92a2-fb3b2bcb5fe9");
        \Phake::when(Arsse::$user)->auth(\Phake::anyParameters())->thenReturn(true);
        $exp = HTTP::respText("SID=12345\nLSID=12345\nAuth=12345\nexpires_in=604800\n");
        $act = $this->h->dispatch($r);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$user)->auth("john.doe@example.com", "secret");
        \Phake::verify(Arsse::$db)->tokenCreate("john.doe@example.com", "reader.login", null, Date::normalize(self::NOW)->add(new \DateInterval("P7D")));
    }
}
