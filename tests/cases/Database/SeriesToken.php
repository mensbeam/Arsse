<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use Phake;

trait SeriesToken {
    protected function setUpSeriesToken() {
        // set up the test data
        $past  = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $future = gmdate("Y-m-d H:i:s", strtotime("now + 1 minute"));
        $faroff = gmdate("Y-m-d H:i:s", strtotime("now + 1 hour"));
        $old = gmdate("Y-m-d H:i:s", strtotime("now - 2 days"));
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                ],
                'rows' => [
                    ["jane.doe@example.com", ""],
                    ["john.doe@example.com", ""],
                ],
            ],
            'arsse_tokens' => [
                'columns' => [
                    'id'      => "str",
                    'class'   => "str",
                    'user'   => "str",
                    'expires' => "datetime",
                ],
                'rows' => [
                    ["80fa94c1a11f11e78667001e673b2560", "fever.login", "jane.doe@example.com", $faroff],
                    ["27c6de8da13311e78667001e673b2560", "fever.login", "jane.doe@example.com", $past], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "class.class", "jane.doe@example.com", null],
                    ["da772f8fa13c11e78667001e673b2560", "class.class", "john.doe@example.com", $future],
                ],
            ],
        ];
    }

    protected function tearDownSeriesToken() {
        unset($this->data);
    }

    public function testLookUpAValidToken() {
        $exp1 = [
            'id' => "80fa94c1a11f11e78667001e673b2560",
            'class' => "fever.login",
            'user' => "jane.doe@example.com"
        ];
        $exp2 = [
            'id' => "da772f8fa13c11e78667001e673b2560",
            'class' => "class.class",
            'user' => "john.doe@example.com"
        ];
        $this->assertArraySubset($exp1, Arsse::$db->tokenLookup("fever.login", "80fa94c1a11f11e78667001e673b2560"));
        $this->assertArraySubset($exp2, Arsse::$db->tokenLookup("class.class", "da772f8fa13c11e78667001e673b2560"));
        // token lookup should not check authorization
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertArraySubset($exp1, Arsse::$db->tokenLookup("fever.login", "80fa94c1a11f11e78667001e673b2560"));
    }

    public function testLookUpAMissingToken() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("class", "thisTokenDoesNotExist");
    }

    public function testLookUpAnExpiredToken() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("fever.login", "27c6de8da13311e78667001e673b2560");
    }

    public function testLookUpATokenOfTheWrongClass() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tokenLookup("some.class", "80fa94c1a11f11e78667001e673b2560");
    }

    public function testCreateAToken() {
        $user = "jane.doe@example.com";
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "class", "expires", "user"]]);
        $id = Arsse::$db->tokenCreate($user, "fever.login");
        $state['arsse_tokens']['rows'][] = [$id, "fever.login", null, $user];
        $this->compareExpectations($state);
        $id = Arsse::$db->tokenCreate($user, "fever.login", null, new \DateTime("2020-01-01T00:00:00Z"));
        $state['arsse_tokens']['rows'][] = [$id, "fever.login", "2020-01-01 00:00:00", $user];
        $this->compareExpectations($state);
        Arsse::$db->tokenCreate($user, "fever.login", "token!", new \DateTime("2021-01-01T00:00:00Z"));
        $state['arsse_tokens']['rows'][] = ["token!", "fever.login", "2021-01-01 00:00:00", $user];
        $this->compareExpectations($state);
    }

    public function testCreateATokenForAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->tokenCreate("fever.login", "jane.doe@example.biz");
    }

    public function testCreateATokenWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tokenCreate("fever.login", "jane.doe@example.com");
    }

    public function testRevokeAToken() {
        $user = "jane.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "fever.login", $id));
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "expires", "user"]]);
        unset($state['arsse_tokens']['rows'][0]);
        $this->compareExpectations($state);
        // revoking a token which does not exist is not an error
        $this->assertFalse(Arsse::$db->tokenRevoke($user, "fever.login", $id));
    }

    public function testRevokeAllTokens() {
        $user = "jane.doe@example.com";
        $state = $this->primeExpectations($this->data, ['arsse_tokens' => ["id", "expires", "user"]]);
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "fever.login"));
        unset($state['arsse_tokens']['rows'][0]);
        unset($state['arsse_tokens']['rows'][1]);
        $this->compareExpectations($state);
        $this->assertTrue(Arsse::$db->tokenRevoke($user, "class.class"));
        unset($state['arsse_tokens']['rows'][2]);
        $this->compareExpectations($state);
        // revoking tokens which do not exist is not an error
        $this->assertFalse(Arsse::$db->tokenRevoke($user, "unknown.class"));
    }

    public function testRevokeATokenWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tokenRevoke("jane.doe@example.com", "fever.login");
    }
}
