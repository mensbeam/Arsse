<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\FeedHQ\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Reader::class)]
#[CoversClass(\JKingWeb\Arsse\REST\Reader\Exception::class)]
class TestReaderFeedHQ extends TestReader {
    public function setUp(): void {
        $this->setUpTest(Reader::class);
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/reader/api/0".$target, "/reader/api/0", ['Accept' => "application/json"], [], $data, "application/x-www-form-urlencoded", [], $user));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testIssuePostTokens(bool $existing): void {
        $user = "john.doe@example.com";
        $token = "ivSJ+XWZMktLeqgRbw+D0gTAhXlZCUv/FT6VVMfH2iENJP8yfpXK3pGiZ";
        $bogus = "ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ";
        \Phake::when(Arsse::$db)->tokenList(\Phake::anyParameters())->thenReturn(new Result($existing ? [['id' => $token]] : []));
        \Phake::when(Arsse::$db)->tokenCreate(\Phake::anyParameters())->thenReturn($bogus);
        $act = $this->req("GET", "/token", "", $user);
        \Phake::verify(Arsse::$db)->tokenList($user, "reader.post");
        if ($existing) {
            \Phake::verify(Arsse::$db, \Phake::never())->tokenCreate(\Phake::anyParameters());
            $exp = HTTP::respText("$token");
        } else {
            $random = "";
            \Phake::verify(Arsse::$db)->tokenCreate($user, "reader.post", \Phake::capture($random));
            $exp = HTTP::respText("$random");
            $this->assertNotEquals($token, $random);
            $this->assertNotEquals($bogus, $random);
            $this->assertSame(57, strlen($random));
        }
        $this->assertMessage($exp, $act);
    }

    public static function provideTokenChecks(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        $failure = self::respError("401", 401, ['X-Reader-Google-Bad-Token' => "true"]);
        $routes = ["/disable-tag", "/edit-tag", "/mark-all-as-read", "/rename-tag", "/subscription/edit", "/subscription/quickadd"];
        foreach ($routes as $target) {
            yield [Reader::class, $target, "T=12345",   $success];
            yield [Reader::class, $target, "T=12345\n", $failure];
            yield [Reader::class, $target, "",          $failure];
            yield [Reader::class, $target, "T=",        $failure];
            yield [Reader::class, $target, "T=x",       $failure];
            yield [Reader::class, $target, "T=56789",   $failure];
            yield [Reader::class, $target, "T=\n",      $failure];
            yield [Reader::class, $target, "T=x\n",     $failure];
            yield [Reader::class, $target, "T=56789\n", $failure];
        }
    }
}
