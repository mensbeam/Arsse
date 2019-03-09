<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use Phake;

trait SeriesSession {
    protected function setUpSeriesSession() {
        // set up the configuration
        static::setConf([
            'userSessionTimeout'  => "PT1H",
            'userSessionLifetime' => "PT24H",
        ]);
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
            'arsse_sessions' => [
                'columns' => [
                    'id'      => "str",
                    'user'    => "str",
                    'created' => "datetime",
                    'expires' => "datetime",
                ],
                'rows' => [
                    ["80fa94c1a11f11e78667001e673b2560", "jane.doe@example.com", $past, $faroff],
                    ["27c6de8da13311e78667001e673b2560", "jane.doe@example.com", $past, $past], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "jane.doe@example.com", $old, $future], // too old
                    ["da772f8fa13c11e78667001e673b2560", "john.doe@example.com", $past, $future],
                ],
            ],
        ];
    }

    protected function tearDownSeriesSession() {
        unset($this->data);
    }

    public function testResumeAValidSession() {
        $exp1 = [
            'id' => "80fa94c1a11f11e78667001e673b2560",
            'user' => "jane.doe@example.com"
        ];
        $exp2 = [
            'id' => "da772f8fa13c11e78667001e673b2560",
            'user' => "john.doe@example.com"
        ];
        $this->assertArraySubset($exp1, Arsse::$db->sessionResume("80fa94c1a11f11e78667001e673b2560"));
        $this->assertArraySubset($exp2, Arsse::$db->sessionResume("da772f8fa13c11e78667001e673b2560"));
        $now = time();
        // sessions near timeout should be refreshed automatically
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        $state['arsse_sessions']['rows'][3][2] = Date::transform(Date::add(Arsse::$conf->userSessionTimeout, $now), "sql");
        $this->compareExpectations($state);
        // session resumption should not check authorization
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertArraySubset($exp1, Arsse::$db->sessionResume("80fa94c1a11f11e78667001e673b2560"));
    }

    public function testResumeAMissingSession() {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("thisSessionDoesNotExist");
    }

    public function testResumeAnExpiredSession() {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("27c6de8da13311e78667001e673b2560");
    }

    public function testResumeAStaleSession() {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("ab3b3eb8a13311e78667001e673b2560");
    }

    public function testCreateASession() {
        $user = "jane.doe@example.com";
        $id = Arsse::$db->sessionCreate($user);
        $now = time();
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        $state['arsse_sessions']['rows'][] = [$id, Date::transform($now, "sql"), Date::transform(Date::add(Arsse::$conf->userSessionTimeout, $now), "sql"), $user];
        $this->compareExpectations($state);
    }

    public function testCreateASessionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->sessionCreate("jane.doe@example.com");
    }

    public function testDestroyASession() {
        $user = "jane.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertTrue(Arsse::$db->sessionDestroy($user, $id));
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        unset($state['arsse_sessions']['rows'][0]);
        $this->compareExpectations($state);
        // destroying a session which does not exist is not an error
        $this->assertFalse(Arsse::$db->sessionDestroy($user, $id));
    }

    public function testDestroyASessionForTheWrongUser() {
        $user = "john.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertFalse(Arsse::$db->sessionDestroy($user, $id));
    }

    public function testDestroyASessionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->sessionDestroy("jane.doe@example.com", "80fa94c1a11f11e78667001e673b2560");
    }
}
