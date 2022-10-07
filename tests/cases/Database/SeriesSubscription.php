<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use GuzzleHttp\Exception\ClientException;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\Misc\Date;

trait SeriesSubscription {
    public function setUpSeriesSubscription(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "", 1],
                    ["john.doe@example.com", "", 2],
                    ["jill.doe@example.com", "", 3],
                    ["jack.doe@example.com", "", 4],
                ],
            ],
            'arsse_folders' => [
                'columns' => ["id", "owner", "parent", "name"],
                'rows'    => [
                    [1, "john.doe@example.com", null, "Technology"],
                    [2, "john.doe@example.com",    1, "Software"],
                    [3, "john.doe@example.com",    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, "Politics"],
                    [5, "john.doe@example.com", null, "Politics"],
                    [6, "john.doe@example.com",    2, "Politics"],
                ],
            ],
            'arsse_icons' => [
                'columns' => ["id", "url", "data"],
                'rows'    => [
                    [1,"http://example.com/favicon.ico", "ICON DATA"],
                    [2,"http://example.net/favicon.ico", null],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "feed_title", "updated", "next_fetch", "icon", "title", "folder", "pinned", "order_type", "keep_rule", "block_rule", "scrape", "deleted", "modified"],
                'rows'    => [
                    [1, "john.doe@example.com", "http://example.com/feed2", "eek", Date::transform("now - 1 hour", "sql"), Date::transform("now - 1 hour", "sql"), 1,    null,  null, 1, 2, null, null,  0, 0, Date::transform("now - 1 hour", "sql")],
                    [2, "jane.doe@example.com", "http://example.com/feed2", "eek", Date::transform("now - 1 hour", "sql"), Date::transform("now - 1 hour", "sql"), 1,    null,  null, 0, 0, null, null,  0, 0, Date::transform("now - 1 hour", "sql")],
                    [3, "john.doe@example.com", "http://example.com/feed3", "Ack", Date::transform("now + 1 hour", "sql"), Date::transform("now + 1 hour", "sql"), 2,    "Ook", 2,    0, 1, null, null,  0, 0, Date::transform("now - 1 hour", "sql")],
                    [4, "jill.doe@example.com", "http://example.com/feed2", "eek", Date::transform("now - 1 hour", "sql"), Date::transform("now - 1 hour", "sql"), 1,    null,  null, 0, 0, null, null,  0, 0, Date::transform("now - 1 hour", "sql")],
                    [5, "jack.doe@example.com", "http://example.com/feed2", "eek", Date::transform("now - 1 hour", "sql"), Date::transform("now - 1 hour", "sql"), 1,    null,  null, 1, 2, "",   "3|E", 0, 0, Date::transform("now - 1 hour", "sql")],
                    [6, "john.doe@example.com", "http://example.com/feed4", "Foo", Date::transform("now + 1 hour", "sql"), Date::transform("now + 1 hour", "sql"), null, "Bar", 3,    0, 0, null, null,  0, 0, Date::transform("now - 1 hour", "sql")],
                    [7, "john.doe@example.com", "http://example.com/feed1", "ook", Date::transform("now + 6 hour", "sql"), Date::transform("now - 1 hour", "sql"), null, null,  null, 0, 0, null, null,  0, 1, Date::transform("now - 1 hour", "sql")],
                ],
            ],
            'arsse_tags' => [
                'columns' => ["id", "owner", "name"],
                'rows'    => [
                    [1,"john.doe@example.com","Interesting"],
                    [2,"john.doe@example.com","Fascinating"],
                    [3,"jane.doe@example.com","Boring"],
                    [4,"john.doe@example.com","Lonely"],
                ],
            ],
            'arsse_tag_members' => [
                'columns' => ["tag", "subscription", "assigned"],
                'rows'    => [
                    [1,1,1],
                    [1,3,0],
                    [2,1,1],
                    [2,3,1],
                    [3,2,1],
                ],
            ],
            'arsse_articles' => [
                'columns' => ["id", "subscription", "url_title_hash", "url_content_hash", "title_content_hash", "title", "read", "starred", "hidden"],
                'rows'    => [
                    [1,  1, "", "", "", "Title 1", 1, 0, 0],
                    [2,  1, "", "", "", "Title 2", 0, 0, 0],
                    [3,  1, "", "", "", "Title 3", 0, 0, 0],
                    [4,  1, "", "", "", "Title 4", 0, 0, 0],
                    [5,  1, "", "", "", "Title 5", 0, 0, 0],
                    [6,  2, "", "", "", "Title 1", 1, 0, 0],
                    [7,  2, "", "", "", "Title 2", 1, 0, 0],
                    [8,  2, "", "", "", "Title 3", 1, 0, 0],
                    [9,  2, "", "", "", "Title 4", 1, 0, 0],
                    [10, 2, "", "", "", "Title 5", 1, 0, 0],
                    [11, 4, "", "", "", "Title 1", 0, 0, 0],
                    [12, 4, "", "", "", "Title 2", 0, 0, 0],
                    [13, 4, "", "", "", "Title 3", 0, 0, 0],
                    [14, 4, "", "", "", "Title 4", 0, 0, 0],
                    [15, 4, "", "", "", "Title 5", 0, 0, 0],
                    [16, 5, "", "", "", "Title 1", 1, 0, 0],
                    [17, 5, "", "", "", "Title 2", 0, 0, 0],
                    [18, 5, "", "", "", "Title 3", 1, 0, 1],
                    [19, 5, "", "", "", "Title 4", 0, 0, 0],
                    [20, 5, "", "", "", "Title 5", 0, 0, 1],
                    [21, 3, "", "", "", "Title 6", 0, 0, 0],
                    [22, 3, "", "", "", "Title 7", 1, 0, 0],
                    [23, 3, "", "", "", "Title 8", 0, 0, 0],
                ],
            ],
            'arsse_editions' => [
                'columns' => ["id", "article"],
                'rows'    => [
                    [1,  1],
                    [2,  2],
                    [3,  3],
                    [4,  4],
                    [5,  5],
                    [6,  6],
                    [7,  7],
                    [8,  8],
                    [9,  9],
                    [10, 10],
                    [11, 11],
                    [12, 12],
                    [13, 13],
                    [14, 14],
                    [15, 15],
                    [16, 16],
                    [17, 17],
                    [18, 18],
                    [19, 19],
                    [20, 20],
                    [21, 21],
                    [22, 22],
                    [23, 23],
                ],
            ],
            'arsse_categories' => [
                'columns' => ["article", "name"],
                'rows'    => [
                    [1,  "A"],
                    [2,  "B"],
                    [4,  "D"],
                    [5,  "E"],
                    [6,  "A"],
                    [7,  "B"],
                    [9,  "D"],
                    [10, "E"],
                    [11, "A"],
                    [12, "B"],
                    [14, "D"],
                    [15, "E"],
                    [16, "A"],
                    [17, "B"],
                    [19, "D"],
                    [20, "E"],
                    [21, "F"],
                    [22, "G"],
                    [23, "H"],
                ],
            ],
        ];
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesSubscription(): void {
        unset($this->data, $this->user);
    }

    public function testReserveASubscription(): void {
        $url = "http://example.com/feed5";
        $exp = $this->nextID("arsse_subscriptions");
        $act = Arsse::$db->subscriptionReserve($this->user, $url, "", "", false);
        $this->assertSame($exp, $act);
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][] = [$exp, $this->user, $url, 1, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReserveADeletedSubscription(): void {
        $url = "http://example.com/feed1";
        $exp = 7;
        $act = Arsse::$db->subscriptionReserve($this->user, $url, "", "", false);
        $this->assertSame($exp, $act);
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][6] = [$exp, $this->user, $url, 1, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReserveASubscriptionWithPassword(): void {
        $url = "http://john:secret@example.com/feed5";
        $exp = $this->nextID("arsse_subscriptions");
        $act = Arsse::$db->subscriptionReserve($this->user, "http://example.com/feed5", "john", "secret", false);
        $this->assertSame($exp, $act);
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][] = [$exp, $this->user, $url, 1, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReserveADuplicateSubscription(): void {
        $url = "http://example.com/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionReserve($this->user, $url, "", "", false);
    }

    public function testReserveASubscriptionWithDiscovery(): void {
        $exp = $this->nextID("arsse_subscriptions");
        $act = Arsse::$db->subscriptionReserve($this->user, "http://localhost:8000/Feed/Discovery/Valid");
        $this->assertSame($exp, $act);
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][] = [$exp, $this->user, "http://localhost:8000/Feed/Discovery/Feed", 1, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRevealASubscription(): void {
        $url = "http://example.com/feed1";
        $this->assertNull(Arsse::$db->subscriptionReveal($this->user, 1, 7));
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][6] = [7, $this->user, $url, 0, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddASubscription(): void {
        $url = "http://example.org/feed5";
        $id = $this->nextID("arsse_subscriptions");
        $db = $this->partialMock(Database::class, static::$drv);
        $db->subscriptionUpdate->returns(true);
        $db->subscriptionPropertiesSet->returns(true);
        Arsse::$db = $db->get();
        try {
            $this->assertSame($id, Arsse::$db->subscriptionAdd($this->user, $url, "", "", false, ['order_type' => 2]));
        } finally {
            $db->subscriptionUpdate->calledWith($this->user, $id, true);
            $db->subscriptionPropertiesSet->calledWith($this->user, $id, ['order_type' => 2]);
            $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
            $state['arsse_subscriptions']['rows'][] = [$id, $this->user, $url, 0, Date::transform("now", "sql")];
            $this->compareExpectations(static::$drv, $state);
        }
    }

    public function testAddASubscriptionToAnInvalidFeed(): void {
        $url = "http://example.org/feed5";
        $id = $this->nextID("arsse_subscriptions");
        $db = $this->partialMock(Database::class, static::$drv);
        $db->subscriptionUpdate->throws(new FeedException("", ['url' => $url], $this->mockGuzzleException(ClientException::class, "", 404)));
        $db->subscriptionPropertiesSet->returns(true);
        Arsse::$db = $db->get();
        $this->assertException("invalidUrl", "Feed");
        try {
            Arsse::$db->subscriptionAdd($this->user, $url, "", "", false, ['order_type' => 2]);
        } finally {
            $db->subscriptionUpdate->calledWith($this->user, $id, true);
            $db->subscriptionPropertiesSet->calledWith($this->user, $id, ['order_type' => 2]);
            $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
            $this->compareExpectations(static::$drv, $state);
        }
    }

    public function testRemoveASubscription(): void {
        $this->assertTrue(Arsse::$db->subscriptionRemove($this->user, 1));
        $state = $this->primeExpectations($this->data, ['arsse_subscriptions' => ["id", "owner", "url", "deleted", "modified"]]);
        $state['arsse_subscriptions']['rows'][0] = [1, $this->user, "http://example.com/feed2", 1, Date::transform("now", "sql")];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 2112);
    }

    public function testRemoveADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 7);
    }

    public function testRemoveAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, -1);
    }

    public function testRemoveASubscriptionForTheWrongOwner(): void {
        $this->user = "jane.doe@example.com";
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 1);
    }

    public function testListSubscriptions(): void {
        $exp = [
            [
                'url'             => "http://example.com/feed2",
                'title'           => "eek",
                'folder'          => null,
                'top_folder'      => null,
                'folder_name'     => null,
                'top_folder_name' => null,
                'unread'          => 4,
                'pinned'          => 1,
                'order_type'      => 2,
                'icon_url'        => "http://example.com/favicon.ico",
                'icon_id'         => 1,
            ],
            [
                'url'             => "http://example.com/feed3",
                'title'           => "Ook",
                'folder'          => 2,
                'top_folder'      => 1,
                'folder_name'     => "Software",
                'top_folder_name' => "Technology",
                'unread'          => 2,
                'pinned'          => 0,
                'order_type'      => 1,
                'icon_url'        => "http://example.net/favicon.ico",
                'icon_id'         => null,
            ],
            [
                'url'             => "http://example.com/feed4",
                'title'           => "Bar",
                'folder'          => 3,
                'top_folder'      => 1,
                'folder_name'     => "Rocketry",
                'top_folder_name' => "Technology",
                'unread'          => 0,
                'pinned'          => 0,
                'order_type'      => 0,
                'icon_url'        => null,
                'icon_id'         => null,
            ],
        ];
        $this->assertResult($exp, Arsse::$db->subscriptionList($this->user));
        $this->assertArraySubset($exp[0], Arsse::$db->subscriptionPropertiesGet($this->user, 1));
        $this->assertArraySubset($exp[1], Arsse::$db->subscriptionPropertiesGet($this->user, 3));
        // test that an absence of marks does not corrupt unread count
        $exp = [
            [
                'url'        => "http://example.com/feed2",
                'title'      => "eek",
                'folder'     => null,
                'top_folder' => null,
                'unread'     => 5,
                'pinned'     => 0,
                'order_type' => 0,
            ],
        ];
        $this->assertResult($exp, Arsse::$db->subscriptionList("jill.doe@example.com"));
    }

    public function testListSubscriptionsInAFolder(): void {
        $exp = [
            [
                'url'        => "http://example.com/feed2",
                'title'      => "eek",
                'folder'     => null,
                'top_folder' => null,
                'unread'     => 4,
                'pinned'     => 1,
                'order_type' => 2,
            ],
        ];
        $this->assertResult($exp, Arsse::$db->subscriptionList($this->user, null, false));
    }

    public function testListSubscriptionsWithRecursion(): void {
        $exp = [
            [
                'url'        => "http://example.com/feed3",
                'title'      => "Ook",
                'folder'     => 2,
                'top_folder' => 1,
                'unread'     => 2,
                'pinned'     => 0,
                'order_type' => 1,
            ],
        ];
        $this->assertResult($exp, Arsse::$db->subscriptionList($this->user, 2));
    }

    public function testListSubscriptionsInAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionList($this->user, 4);
    }

    public function testCountSubscriptions(): void {
        $this->assertSame(3, Arsse::$db->subscriptionCount($this->user));
        $this->assertSame(1, Arsse::$db->subscriptionCount($this->user, 2));
    }

    public function testCountSubscriptionsInAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionCount($this->user, 4);
    }

    public function testGetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, 2112);
    }

    public function testGetThePropertiesOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, 7);
    }

    public function testGetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, -1);
    }

    public function testSetThePropertiesOfASubscription(): void {
        $this->markTestIncomplete();
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, [
            'title'      => "Ook Ook",
            'folder'     => 3,
            'pinned'     => false,
            'scrape'     => true,
            'order_type' => 0,
            'keep_rule'  => "ook",
            'block_rule' => "eek",
        ]);
        $state = $this->primeExpectations($this->data, [
            'arsse_subscriptions' => ['id','owner','feed_title', 'title','folder','pinned','order_type','keep_rule','block_rule','scrape'],
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com","eek","Ook Ook",3,0,0,"ook","eek",1];
        $this->compareExpectations(static::$drv, $state);
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, [
            'title'      => null,
            'keep_rule'  => null,
            'block_rule' => null,
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com","eek",null,3,0,0,null,null,1];
        $this->compareExpectations(static::$drv, $state);
        // making no changes is a valid result
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['unhinged' => true]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testMoveASubscriptionToAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['folder' => 4]);
    }

    public function testMoveASubscriptionToTheRootFolder(): void {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 3, ['folder' => null]));
    }

    /** @dataProvider provideInvalidSubscriptionProperties */
    public function testSetThePropertiesOfASubscriptionToInvalidValues(array $data, string $exp): void {
        $this->assertException($exp, "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, $data);
    }

    public function provideInvalidSubscriptionProperties(): iterable {
        return [
            'Empty title'           => [['title' => ""],       "missing"],
            'Whitespace title'      => [['title' => "    "],   "whitespace"],
            'Non-string title'      => [['title' => []],       "typeViolation"],
            'Non-string keep rule'  => [['keep_rule' => 0],    "typeViolation"],
            'Invalid keep rule'     => [['keep_rule' => "*"],  "invalidValue"],
            'Non-string block rule' => [['block_rule' => 0],   "typeViolation"],
            'Invalid block rule'    => [['block_rule' => "*"], "invalidValue"],
        ];
    }

    public function testRenameASubscriptionToZero(): void {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => 0]));
    }

    public function testSetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 2112, ['folder' => null]);
    }

    public function testSetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, -1, ['folder' => null]);
    }

    public function testRetrieveTheFaviconOfASubscription(): void {
        $exp = "http://example.com/favicon.ico";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon(null, 1)['url']);
        $this->assertSame($exp, Arsse::$db->subscriptionIcon(null, 2)['url']);
        $this->assertSame(null, Arsse::$db->subscriptionIcon(null, 6));
    }

    public function testRetrieveTheFaviconOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionIcon(null, -2112);
    }

    public function testRetrieveTheFaviconOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionIcon(null, 7);
    }

    public function testRetrieveTheFaviconOfASubscriptionWithUser(): void {
        $exp = "http://example.com/favicon.ico";
        $user = "john.doe@example.com";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon($user, 1)['url']);
        $this->assertSame(null, Arsse::$db->subscriptionIcon($user, 6));
        $user = "jane.doe@example.com";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon($user, 2)['url']);
    }

    public function testRetrieveTheFaviconOfASubscriptionOfTheWrongUser(): void {
        $user = "john.doe@example.com";
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionIcon($user, 2);
    }

    public function testListTheTagsOfASubscription(): void {
        $this->assertEquals([1,2], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 1));
        $this->assertEquals([2], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 3));
        $this->assertEquals(["Fascinating","Interesting"], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 1, true));
        $this->assertEquals(["Fascinating"], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 3, true));
    }

    public function testListTheTagsOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionTagsGet($this->user, 101);
    }

    public function testListTheTagsOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionTagsGet($this->user, 7);
    }

    public function testGetRefreshTimeOfASubscription(): void {
        $user = "john.doe@example.com";
        $this->assertTime(strtotime("now + 1 hour"), Arsse::$db->subscriptionRefreshed($user));
        $this->assertTime(strtotime("now - 1 hour"), Arsse::$db->subscriptionRefreshed($user, 1));
    }

    public function testGetRefreshTimeOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRefreshed("john.doe@example.com", 2);
    }

    public function testGetRefreshTimeOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRefreshed("john.doe@example.com", 7);
    }

    public function testSetTheFilterRulesOfASubscriptionCheckingMarks(): void {
        $this->markTestIncomplete();
        Arsse::$db->subscriptionPropertiesSet("jack.doe@example.com", 5, ['keep_rule' => "1|B|3|D", 'block_rule' => "4"]);
        $state = $this->primeExpectations($this->data, ['arsse_marks' => ['article', 'subscription', 'hidden']]);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][10][2] = 1;
        $this->compareExpectations(static::$drv, $state);
    }
}
