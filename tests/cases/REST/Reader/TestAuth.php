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
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Auth::class)]
class TestAuth extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenCreate->thenReturn("12345");
        $this->h = new Auth;
    }

    #[DataProvider("provideAuthentications")]
    public function testAuthenticateAUser(string $method, string $get, string $post, array $cred, bool $out, ResponseInterface $exp): void {
        \Phake::when(Arsse::$user)->auth->thenReturn($out);
        $r = $this->serverRequest($method, "/api/greader.php/accounts/ClientLogin?".$get, "/api/greader.php/accounts/ClientLogin", [], [], $post, "application/x-www-form-urlencoded", [], null);
        $act = $this->h->dispatch($r);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$user)->auth(...$cred);
    }

    public static function provideAuthentications(): iterable {
        $token = "12345";
        return [
            ["GET",  "Email=ook&Passwd=eek", "",                     ["ook", "eek"], true, HTTP::respText("SID=$token\nLSID=$token\nAuth=$token")],
            ["GET",  "",                     "Email=ook&Passwd=eek", ["ook", "eek"], true, HTTP::respText("SID=$token\nLSID=$token\nAuth=$token")],
            ["GET",  "Email=ook",            "Passwd=eek",           ["ook", "eek"], true, HTTP::respText("SID=$token\nLSID=$token\nAuth=$token")],
            ["GET",  "Passwd=eek",           "Email=ook",            ["ook", "eek"], true, HTTP::respText("SID=$token\nLSID=$token\nAuth=$token")],
        ];
    }
}
