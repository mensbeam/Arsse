<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversMethod;

trait SeriesToken {
    protected static $drv;

    protected function setUpSeriesToken(): void {
        // set up the test data
        $past = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $future = gmdate("Y-m-d H:i:s", strtotime("now + 1 minute"));
        $faroff = gmdate("Y-m-d H:i:s", strtotime("now + 1 hour"));
        $old = gmdate("Y-m-d H:i:s", strtotime("now - 2 days"));
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_tokens' => [
                'columns' => ["id", "class", "user", "expires", "data"],
                'rows'    => [
                    ["80fa94c1a11f11e78667001e673b2560", "fever.login",    "jane.doe@example.com", $faroff, null],
                    ["27c6de8da13311e78667001e673b2560", "fever.login",    "jane.doe@example.com", $past, null], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "class.class",    "jane.doe@example.com", null, null],
                    ["da772f8fa13c11e78667001e673b2560", "class.class",    "john.doe@example.com", $future, null],
                    ["A",                                "miniflux.login", "jane.doe@example.com", null, "Label 1"],
                    ["B",                                "miniflux.login", "jane.doe@example.com", null, "Label 2"],
                    ["C",                                "miniflux.login", "john.doe@example.com", null, "Label 1"],
                ],
            ],
        ];
    }

    protected function tearDownSeriesToken(): void {
        unset($this->data);
    }

    //#[CoversMethod(Database::class, "tokenLookup")]
    public function testLookUpAValidToken(): void {
        $exp1 = [
            'id'    => "80fa94c1a11f11e78667001e673b2560",
            'class' => "fever.login",
            'user'  => "jane.doe@example.com",
        ];
        $exp2 = [
            'id'    => "da772f8fa13c11e78667001e673b2560",
            'class' => "class.class",
            'user'  => "john.doe@example.com",
        ];
        $exp3 = [
            'id'    => "ab3b3eb8a13311e78667001e673b2560",
            'class' => "class.class",
            'user'  => "jane.doe@example.com",
        ];
        $this->assertArraySubset($exp1, Arsse::$db->tokenLookup("fever.login", "80fa94c1a11f11e78667001e673b2560"));
        $this->assertArraySubset($exp2, Arsse::$db->tokenLookup("class.class", "da772f8fa13c11e78667001e673b2560"));
        $this->assertArraySubset($exp3, Arsse::$db->tokenLookup("class.class", "ab3b3eb8a13311e78667001e673b2560"));
    }

    //#[CoversMethod(Database::class, "tokenLookup")]
    public function testLookUpAMissingToken(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("class", "thisTokenDoesNotExist");
    }

    //#[CoversMethod(Database::class, "tokenLookup")]
    public function testLookUpAnExpiredToken(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("fever.login", "27c6de8da13311e78667001e673b2560");
    }

    //#[CoversMethod(Database::class, "tokenLookup")]
    public function testLookUpATokenOfTheWrongClass(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("some.class", "80fa94c1a11f11e78667001e673b2560");
    }

    //#[CoversMethod(Database::class, "tokenCreate")]
    public function testCreateAToken(): void {
        $user = "jane.doe@example.com";
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "class", "expires", "user"]]);
        $id = Arsse::$db->tokenCreate($user, "fever.login");
        $state['arsse_tokens']['rows'][] = [$id, "fever.login", null, $user];
        $this->compareExpectations(static::$drv, $state);
        $id = Arsse::$db->tokenCreate($user, "fever.login", null, new \DateTime("2020-01-01T00:00:00Z"));
        $state['arsse_tokens']['rows'][] = [$id, "fever.login", "2020-01-01 00:00:00", $user];
        $this->compareExpectations(static::$drv, $state);
        Arsse::$db->tokenCreate($user, "fever.login", "token!", new \DateTime("2021-01-01T00:00:00Z"));
        $state['arsse_tokens']['rows'][] = ["token!", "fever.login", "2021-01-01 00:00:00", $user];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "tokenCreate")]
    public function testCreateATokenForAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->tokenCreate("fever.login", "jane.doe@example.biz");
    }

    //#[CoversMethod(Database::class, "tokenRevoke")]
    public function testRevokeAToken(): void {
        $user = "jane.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "fever.login", $id));
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "expires", "user"]]);
        unset($state['arsse_tokens']['rows'][0]);
        $this->compareExpectations(static::$drv, $state);
        // revoking a token which does not exist is not an error
        $this->assertFalse(Arsse::$db->tokenRevoke($user, "fever.login", $id));
    }

    //#[CoversMethod(Database::class, "tokenRevoke")]
    public function testRevokeAllTokens(): void {
        $user = "jane.doe@example.com";
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "expires", "user"]]);
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "fever.login"));
        unset($state['arsse_tokens']['rows'][0]);
        unset($state['arsse_tokens']['rows'][1]);
        $this->compareExpectations(static::$drv, $state);
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "class.class"));
        unset($state['arsse_tokens']['rows'][2]);
        $this->compareExpectations(static::$drv, $state);
        // revoking tokens which do not exist is not an error
        $this->assertFalse(Arsse::$db->tokenRevoke($user, "unknown.class"));
    }

    //#[CoversMethod(Database::class, "tokenList")]
    public function testListTokens(): void {
        $user = "jane.doe@example.com";
        $exp = [
            ['id' => "A", 'data' => "Label 1"],
            ['id' => "B", 'data' => "Label 2"],
        ];
        $this->assertResult($exp, Arsse::$db->tokenList($user, "miniflux.login"));
    }
}
