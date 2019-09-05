<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesCleanup {
    protected function setUpSeriesCleanup() {
        // set up the configuration
        Arsse::$conf->import([
            'userSessionTimeout'  => "PT1H",
            'userSessionLifetime' => "PT24H",
        ]);
        // set up the test data
        $nowish  = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $yesterday = gmdate("Y-m-d H:i:s", strtotime("now - 1 day"));
        $daybefore = gmdate("Y-m-d H:i:s", strtotime("now - 2 days"));
        $daysago = gmdate("Y-m-d H:i:s", strtotime("now - 7 days"));
        $weeksago = gmdate("Y-m-d H:i:s", strtotime("now - 21 days"));
        $soon = gmdate("Y-m-d H:i:s", strtotime("now + 1 minute"));
        $faroff = gmdate("Y-m-d H:i:s", strtotime("now + 1 hour"));
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
                    'user'   => "str",
                    'expires' => "datetime",
                ],
                'rows' => [
                    ["80fa94c1a11f11e78667001e673b2560", "fever.login", "jane.doe@example.com", $faroff],
                    ["27c6de8da13311e78667001e673b2560", "fever.login", "jane.doe@example.com", $weeksago], // expired
                    ["ab3b3eb8a13311e78667001e673b2560", "class.class", "jane.doe@example.com", null],
                    ["da772f8fa13c11e78667001e673b2560", "class.class", "john.doe@example.com", $soon],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'orphaned'   => "datetime",
                    'size'       => "int",
                ],
                'rows' => [
                    [1,"http://example.com/1","",$daybefore,2],  //latest two articles should be kept
                    [2,"http://example.com/2","",$yesterday,0],
                    [3,"http://example.com/3","",null,0],
                    [4,"http://example.com/4","",$nowish,0],
                ]
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
                ]
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
                ]
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
                ]
            ],
            'arsse_marks' => [
                'columns' => [
                    'article'      => "int",
                    'subscription' => "int",
                    'read'         => "bool",
                    'starred'      => "bool",
                    'modified'     => "datetime",
                ],
                'rows' => [
                    [3,1,0,1,$weeksago],
                    [4,1,1,0,$daysago],
                    [6,1,1,0,$nowish],
                    [6,2,1,0,$weeksago],
                    [8,1,1,0,$weeksago],
                    [9,1,1,0,$daysago],
                    [9,2,1,0,$daysago],
                ]
            ],
        ];
    }

    protected function tearDownSeriesCleanup() {
        unset($this->data);
    }

    public function testCleanUpOrphanedFeeds() {
        Arsse::$db->feedCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds' => ["id","orphaned"]
        ]);
        $state['arsse_feeds']['rows'][0][1] = null;
        unset($state['arsse_feeds']['rows'][1]);
        $state['arsse_feeds']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOrphanedFeedsWithUnlimitedRetention() {
        Arsse::$conf->import([
            'purgeFeeds' => null,
        ]);
        Arsse::$db->feedCleanup();
        $now = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds' => ["id","orphaned"]
        ]);
        $state['arsse_feeds']['rows'][0][1] = null;
        $state['arsse_feeds']['rows'][2][1] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithStandardRetention() {
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"]
        ]);
        foreach ([7,8,9] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithUnlimitedReadRetention() {
        Arsse::$conf->import([
            'purgeArticlesRead' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"]
        ]);
        foreach ([7,8] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithUnlimitedUnreadRetention() {
        Arsse::$conf->import([
            'purgeArticlesUnread' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"]
        ]);
        foreach ([9] as $id) {
            unset($state['arsse_articles']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpOldArticlesWithUnlimitedRetention() {
        Arsse::$conf->import([
            'purgeArticlesRead' => null,
            'purgeArticlesUnread' => null,
        ]);
        Arsse::$db->articleCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id"]
        ]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpExpiredSessions() {
        Arsse::$db->sessionCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_sessions' => ["id"]
        ]);
        foreach ([3,4,5] as $id) {
            unset($state['arsse_sessions']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    public function testCleanUpExpiredTokens() {
        Arsse::$db->tokenCleanup();
        $state = $this->primeExpectations($this->data, [
            'arsse_tokens' => ["id", "class"]
        ]);
        foreach ([2] as $id) {
            unset($state['arsse_tokens']['rows'][$id - 1]);
        }
        $this->compareExpectations(static::$drv, $state);
    }
}
