<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversMethod;

trait SeriesSession {
    protected static $drv;

    protected function setUpSeriesSession(): void {
        // set up the configuration
        static::setConf([
            'userSessionTimeout'  => "PT1H",
            'userSessionLifetime' => "PT24H",
        ]);
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
            'arsse_sessions' => [
                'columns' => ["id", "user", "created", "expires"],
                'rows'    => [
                    ["80fa94c1a11f11e78667001e673b2560", "jane.doe@example.com", $past, $faroff],
                    ["27c6de8da13311e78667001e673b2560", "jane.doe@example.com", $past, $past], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "jane.doe@example.com", $old, $future], // too old
                    ["da772f8fa13c11e78667001e673b2560", "john.doe@example.com", $past, $future],
                ],
            ],
        ];
    }

    protected function tearDownSeriesSession(): void {
        unset($this->data);
    }

    //#[CoversMethod(Database::class, "sessionResume")]
    //#[CoversMethod(Database::class, "sessionExpiringSoon")]
    public function testResumeAValidSession(): void {
        $exp1 = [
            'id'   => "80fa94c1a11f11e78667001e673b2560",
            'user' => "jane.doe@example.com",
        ];
        $exp2 = [
            'id'   => "da772f8fa13c11e78667001e673b2560",
            'user' => "john.doe@example.com",
        ];
        $this->assertArraySubset($exp1, Arsse::$db->sessionResume("80fa94c1a11f11e78667001e673b2560"));
        $this->assertArraySubset($exp2, Arsse::$db->sessionResume("da772f8fa13c11e78667001e673b2560"));
        $now = time();
        // sessions near timeout should be refreshed automatically
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        $state['arsse_sessions']['rows'][3][2] = Date::transform(Date::add(Arsse::$conf->userSessionTimeout, $now), "sql");
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "sessionResume")]
    //#[CoversMethod(Database::class, "sessionExpiringSoon")]
    public function testResumeAMissingSession(): void {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("thisSessionDoesNotExist");
    }

    //#[CoversMethod(Database::class, "sessionResume")]
    //#[CoversMethod(Database::class, "sessionExpiringSoon")]
    public function testResumeAnExpiredSession(): void {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("27c6de8da13311e78667001e673b2560");
    }

    //#[CoversMethod(Database::class, "sessionResume")]
    //#[CoversMethod(Database::class, "sessionExpiringSoon")]
    public function testResumeAStaleSession(): void {
        $this->assertException("invalid", "User", "ExceptionSession");
        Arsse::$db->sessionResume("ab3b3eb8a13311e78667001e673b2560");
    }

    //#[CoversMethod(Database::class, "sessionCreate")]
    public function testCreateASession(): void {
        $user = "jane.doe@example.com";
        $id = Arsse::$db->sessionCreate($user);
        $now = time();
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        $state['arsse_sessions']['rows'][] = [$id, Date::transform($now, "sql"), Date::transform(Date::add(Arsse::$conf->userSessionTimeout, $now), "sql"), $user];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "sessionDestroy")]
    public function testDestroyASession(): void {
        $user = "jane.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertTrue(Arsse::$db->sessionDestroy($user, $id));
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        unset($state['arsse_sessions']['rows'][0]);
        $this->compareExpectations(static::$drv, $state);
        // destroying a session which does not exist is not an error
        $this->assertFalse(Arsse::$db->sessionDestroy($user, $id));
    }

    //#[CoversMethod(Database::class, "sessionDestroy")]
    public function testDestroyAllSessions(): void {
        $user = "jane.doe@example.com";
        $this->assertTrue(Arsse::$db->sessionDestroy($user));
        $state = $this->primeExpectations($this->data, ['arsse_sessions' => ["id", "created", "expires", "user"]]);
        unset($state['arsse_sessions']['rows'][0]);
        unset($state['arsse_sessions']['rows'][1]);
        unset($state['arsse_sessions']['rows'][2]);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "sessionDestroy")]
    public function testDestroyASessionForTheWrongUser(): void {
        $user = "john.doe@example.com";
        $id = "80fa94c1a11f11e78667001e673b2560";
        $this->assertFalse(Arsse::$db->sessionDestroy($user, $id));
    }
}
