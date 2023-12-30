<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use DateTimeImmutable as Date;

trait SeriesCleanup {
    protected static $drv;

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
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_sessions' => [
                'columns' => ["id", "created", "expires", "user"],
                'rows'    => [
                    ["a", $nowish,  $faroff, "jane.doe@example.com"], // not expired and recently created, thus kept
                    ["b", $nowish,  $soon,   "jane.doe@example.com"], // not expired and recently created, thus kept
                    ["c", $daysago, $soon,   "jane.doe@example.com"], // created more than a day ago, thus deleted
                    ["d", $nowish,  $nowish, "jane.doe@example.com"], // recently created but expired, thus deleted
                    ["e", $daysago, $nowish, "jane.doe@example.com"], // created more than a day ago and expired, thus deleted
                ],
            ],
            'arsse_tokens' => [
                'columns' => ["id", "class", "user", "expires"],
                'rows'    => [
                    ["80fa94c1a11f11e78667001e673b2560", "fever.login", "jane.doe@example.com", $faroff],
                    ["27c6de8da13311e78667001e673b2560", "fever.login", "jane.doe@example.com", $weeksago], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "class.class", "jane.doe@example.com", null],
                    ["da772f8fa13c11e78667001e673b2560", "class.class", "john.doe@example.com", $soon],
                ],
            ],
            'arsse_icons' => [
                'columns' => ["id", "url", "orphaned"],
                'rows'    => [
                    [1,'http://localhost:8000/Icon/PNG',$daybefore],
                    [2,'http://localhost:8000/Icon/GIF',$daybefore],
                    [3,'http://localhost:8000/Icon/SVG1',null],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "size", "icon", "deleted", "modified"],
                'rows'    => [
                    // first two subscriptions are used for article cleanup tests: the latest two articles should be kept
                    [1,'jane.doe@example.com',"http://example.com/1",2,null,0,$daybefore],
                    [2,'john.doe@example.com',"http://example.com/1",2,   1,0,$daybefore],
                    // the other subscriptions are used for subscription cleanup
                    [3,'jane.doe@example.com',"http://example.com/2",0,   2,1,$yesterday],
                    [4,'jane.doe@example.com',"http://example.com/4",0,null,1,$nowish],

                ],
            ],
            'arsse_articles' => [
                'columns' => ["id", "subscription", "url_title_hash", "url_content_hash", "title_content_hash", "modified", "read", "starred", "hidden", "marked"],
                'rows'    => [
                    [   1,1,"","","",$weeksago,0,0,0,null],       // is the latest article, thus is kept
                    [   2,1,"","","",$weeksago,0,0,0,null],       // is the second latest article, thus is kept
                    [   3,1,"","","",$weeksago,0,1,0,$weeksago],  // is starred by the user, thus is kept
                    [   4,1,"","","",$weeksago,1,0,0,$yesterday], // does not meet the unread threshold due to a recent mark, thus is kept
                    [   5,1,"","","",$daysago, 0,0,0,null],       // does not meet the unread threshold due to age, thus is kept
                    [   6,1,"","","",$weeksago,1,0,0,$nowish],    // does not meet the read threshold due to a recent mark, thus is kept
                    [   7,1,"","","",$weeksago,0,0,0,null],       // meets the unread threshold without marks, thus is deleted
                    [   8,1,"","","",$weeksago,1,0,0,$weeksago],  // meets the unread threshold even with marks, thus is deleted
                    [   9,1,"","","",$weeksago,1,0,0,$daysago],   // meets the read threshold, thus is deleted
                    [1001,2,"","","",$weeksago,0,0,0,null],       // is the latest article, thus is kept
                    [1002,2,"","","",$weeksago,0,0,0,null],       // is the second latest article, thus is kept
                    [1003,2,"","","",$weeksago,0,0,0,null],       // meets the unread threshold without marks, thus is deleted
                    [1004,2,"","","",$weeksago,0,0,0,null],       // meets the unread threshold without marks, thus is deleted
                    [1005,2,"","","",$daysago, 0,0,0,null],       // does not meet the unread threshold due to age, thus is kept
                    [1006,2,"","","",$weeksago,1,0,0,$weeksago],  // meets the unread threshold even with marks, thus is deleted
                    [1007,2,"","","",$weeksago,0,1,1,$weeksago],  // hidden overrides starred, thus is deleted
                    [1008,2,"","","",$weeksago,0,0,0,null],       // meets the unread threshold without marks, thus is deleted
                    [1009,2,"","","",$weeksago,0,0,1,$daysago],   // meets the read threshold because hidden is equivalent to read, thus is deleted
                ],
            ],
            'arsse_editions' => [
                'columns' => ["id", "article"],
                'rows'    => [
                    [1,1],
                    [2,2],
                    [3,3],
                    [4,4],
                    [5,5],
                    [6,6],
                    [7,7],
                    [8,8],
                    [9,9],
                    [201,1],
                    [102,2],
                    [1001,1001],
                    [1002,1002],
                    [1003,1003],
                    [1004,1004],
                    [1005,1005],
                    [1006,1006],
                    [1007,1007],
                    [1008,1008],
                    [1009,1009],
                    [1201,1001],
                    [1102,1002],
                ],
            ],
        ];
    }

    protected function tearDownSeriesCleanup(): void {
        unset($this->data);
    }

    public function testCleanUpDeletedSubscriptions(): void {
        Arsse::$db->subscriptionCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_subscriptions' => ["id"],
        ]);
        unset($state['arsse_subscriptions']['rows'][2]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpDeletedSubscriptionsWithUnlimitedRetention(): void {
        Arsse::$conf->import([
            'purgeFeeds' => null,
        ]);
        Arsse::$db->subscriptionCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_subscriptions' => ["id"],
        ]);
        $this->compareExpectations(static::$drv, $state);
    }

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

    public function testCleanUpOldArticlesWithStandardRetention(): void {
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        $deleted = [7, 8, 9, 1003, 1004, 1006, 1007, 1008, 1009];
        $stop = sizeof($state['arsse_articles']['rows']);
        for ($a = 0; $a < $stop; $a++) {
            if (in_array($state['arsse_articles']['rows'][$a][0], $deleted)) {
                unset($state['arsse_articles']['rows'][$a]);
            }
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithUnlimitedReadRetention(): void {
        Arsse::$conf->import([
            'purgeArticlesRead' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        $deleted = [7, 8, 1003, 1004, 1006, 1007, 1008];
        $stop = sizeof($state['arsse_articles']['rows']);
        for ($a = 0; $a < $stop; $a++) {
            if (in_array($state['arsse_articles']['rows'][$a][0], $deleted)) {
                unset($state['arsse_articles']['rows'][$a]);
            }
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithUnlimitedUnreadRetention(): void {
        Arsse::$conf->import([
            'purgeArticlesUnread' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"],
        ]);
        $deleted = [8, 9, 1006, 1007, 1009];
        $stop = sizeof($state['arsse_articles']['rows']);
        for ($a = 0; $a < $stop; $a++) {
            if (in_array($state['arsse_articles']['rows'][$a][0], $deleted)) {
                unset($state['arsse_articles']['rows'][$a]);
            }
        }
        $this->compareExpectations(static::$drv, $state);
    }

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
