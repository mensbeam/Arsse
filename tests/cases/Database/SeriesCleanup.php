<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use DateTimeImmutable as Date;

trait SeriesCleanup {
    protected function setUpSeriesCleanup(): void {
        // set up the configuration
        Arsse::$conf->import([
            'userSessionTimeout'  => "PT1H",
            'userSessionLifetime' => "PT24H",
        ]);
        // set up the test data
        $tz = new \DateTimeZone("UTC");
        $nowish = (new Date("now - 1 minute", $tz))->format("Y-m-d H:i:s");
        $yesterday = (new Date("now - 1 day", $tz))->format("Y-m-d H:i:s");
        $daybefore = (new Date("now - 2 days", $tz))->format("Y-m-d H:i:s");
        $daysago = (new Date("now - 7 days", $tz))->format("Y-m-d H:i:s");
        $weeksago = (new Date("now - 21 days", $tz))->format("Y-m-d H:i:s");
        $soon = (new Date("now + 1 minute", $tz))->format("Y-m-d H:i:s");
        $faroff = (new Date("now + 1 hour", $tz))->format("Y-m-d H:i:s");
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                    'num'      => 'int',
                ],
                'rows' => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_sessions' => [
                'columns' => [
                    'id'      => "str",
                    'created' => "datetime",
                    'expires' => "datetime",
                    'user'    => "str",
                ],
                'rows' => [
                    ["a", $nowish,  $faroff, "jane.doe@example.com"], // not expired and recently created, thus kept
                    ["b", $nowish,  $soon,   "jane.doe@example.com"], // not expired and recently created, thus kept
                    ["c", $daysago, $soon,   "jane.doe@example.com"], // created more than a day ago, thus deleted
                    ["d", $nowish,  $nowish, "jane.doe@example.com"], // recently created but expired, thus deleted
                    ["e", $daysago, $nowish, "jane.doe@example.com"], // created more than a day ago and expired, thus deleted
                ],
            ],
            'arsse_tokens' => [
                'columns' => [
                    'id'      => "str",
                    'class'   => "str",
                    'user'    => "str",
                    'expires' => "datetime",
                ],
                'rows' => [
                    ["80fa94c1a11f11e78667001e673b2560", "fever.login", "jane.doe@example.com", $faroff],
                    ["27c6de8da13311e78667001e673b2560", "fever.login", "jane.doe@example.com", $weeksago], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "class.class", "jane.doe@example.com", null],
                    ["da772f8fa13c11e78667001e673b2560", "class.class", "john.doe@example.com", $soon],
                ],
            ],
            'arsse_icons' => [
                'columns' => [
                    'id'       => "int",
                    'url'      => "str",
                    'orphaned' => "datetime",
                ],
                'rows' => [
                    [1,'http://localhost:8000/Icon/PNG',$daybefore],
                    [2,'http://localhost:8000/Icon/GIF',$daybefore],
                    [3,'http://localhost:8000/Icon/SVG1',null],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'orphaned'   => "datetime",
                    'size'       => "int",
                    'icon'       => "int",
                ],
                'rows' => [
                    [1,"http://example.com/1","",$daybefore,2,null],  //latest two articles should be kept
                    [2,"http://example.com/2","",$yesterday,0,2],
                    [3,"http://example.com/3","",null,0,1],
                    [4,"http://example.com/4","",$nowish,0,null],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'    => "int",
                    'owner' => "str",
                    'feed'  => "int",
                ],
                'rows' => [
                    // one feed previously marked for deletion has a subscription again, and so should not be deleted
                    [1,'jane.doe@example.com',1],
                    // other subscriptions exist for article cleanup tests
                    [2,'john.doe@example.com',1],
                ],
            ],
            'arsse_articles' => [
                'columns' => [
                    'id'                 => "int",
                    'feed'               => "int",
                    'url_title_hash'     => "str",
                    'url_content_hash'   => "str",
                    'title_content_hash' => "str",
                    'modified'           => "datetime",
                ],
                'rows' => [
                    [1,1,"","","",$weeksago], // is the latest article, thus is kept
                    [2,1,"","","",$weeksago], // is the second latest article, thus is kept
                    [3,1,"","","",$weeksago], // is starred by one user, thus is kept
                    [4,1,"","","",$weeksago], // does not meet the unread threshold due to a recent mark, thus is kept
                    [5,1,"","","",$daysago],  // does not meet the unread threshold due to age, thus is kept
                    [6,1,"","","",$weeksago], // does not meet the read threshold due to a recent mark, thus is kept
                    [7,1,"","","",$weeksago], // meets the unread threshold without marks, thus is deleted
                    [8,1,"","","",$weeksago], // meets the unread threshold even with marks, thus is deleted
                    [9,1,"","","",$weeksago], // meets the read threshold, thus is deleted
                ],
            ],
            'arsse_editions' => [
                'columns' => [
                    'id'       => "int",
                    'article'  => "int",
                ],
                'rows' => [
                    [1,1],
                    [2,2],
                    [3,3],
                    [4,4],
                    [201,1],
                    [102,2],
                ],
            ],
            'arsse_marks' => [
                'columns' => [
                    'article'      => "int",
                    'subscription' => "int",
                    'read'         => "bool",
                    'starred'      => "bool",
                    'hidden'       => "bool",
                    'modified'     => "datetime",
                ],
                'rows' => [
                    [3,1,0,1,0,$weeksago],
                    [4,1,1,0,0,$daysago],
                    [6,1,1,0,0,$nowish],
                    [6,2,1,0,0,$weeksago],
                    [7,2,0,1,1,$weeksago], // hidden takes precedence over starred
                    [8,1,1,0,0,$weeksago],
                    [9,1,1,0,0,$daysago],
                    [9,2,0,0,1,$daysago], // hidden is the same as read for the purposes of cleanup
                ],
            ],
        ];
    }

    protected function tearDownSeriesCleanup(): void {
        unset($this->data);
    }

    /** @covers \JKingWeb\Arsse\Database::feedCleanup */
    public function testCleanUpOrphanedFeeds(): void {
        Arsse::$db->feedCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds' => ["id","orphaned"],
        ]);
        $state['arsse_feeds']['rows'][0][1] = null;
        unset($state['arsse_feeds']['rows'][1]);
        $state['arsse_feeds']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::feedCleanup */
    public function testCleanUpOrphanedFeedsWithUnlimitedRetention(): void {
        Arsse::$conf->import([
            'purgeFeeds' => null,
        ]);
        Arsse::$db->feedCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds' => ["id","orphaned"],
        ]);
        $state['arsse_feeds']['rows'][0][1] = null;
        $state['arsse_feeds']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::iconCleanup */
    public function testCleanUpOrphanedIcons(): void {
        Arsse::$db->iconCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_icons' => ["id","orphaned"],
        ]);
        $state['arsse_icons']['rows'][0][1] = null;
        unset($state['arsse_icons']['rows'][1]);
        $state['arsse_icons']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::iconCleanup */
    public function testCleanUpOrphanedIconsWithUnlimitedRetention(): void {
        Arsse::$conf->import([
            'purgeFeeds' => null,
        ]);
        Arsse::$db->iconCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_icons' => ["id","orphaned"],
        ]);
        $state['arsse_icons']['rows'][0][1] = null;
        $state['arsse_icons']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::articleCleanup */
    public function testCleanUpOldArticlesWithStandardRetention(): void {
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        foreach ([7,8,9] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::articleCleanup */
    public function testCleanUpOldArticlesWithUnlimitedReadRetention(): void {
        Arsse::$conf->import([
            'purgeArticlesRead' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        foreach ([7,8] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::articleCleanup */
    public function testCleanUpOldArticlesWithUnlimitedUnreadRetention(): void {
        Arsse::$conf->import([
            'purgeArticlesUnread' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        foreach ([9] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::articleCleanup */
    public function testCleanUpOldArticlesWithUnlimitedRetention(): void {
        Arsse::$conf->import([
            'purgeArticlesRead'   => null,
            'purgeArticlesUnread' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::sessionCleanup */
    public function testCleanUpExpiredSessions(): void {
        Arsse::$db->sessionCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_sessions' => ["id"],
        ]);
        foreach ([3,4,5] as $id) {
            unset($state['arsse_sessions']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::tokenCleanup */
    public function testCleanUpExpiredTokens(): void {
        Arsse::$db->tokenCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_tokens' => ["id", "class"],
        ]);
        foreach ([2] as $id) {
            unset($state['arsse_tokens']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }
}
