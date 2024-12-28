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
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

trait SeriesSubscription {
    public function setUpSeriesSubscription(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                    'num'      => 'int',
                ],
                'rows' => [
                    ["jane.doe@example.com", "", 1],
                    ["john.doe@example.com", "", 2],
                    ["jill.doe@example.com", "", 3],
                    ["jack.doe@example.com", "", 4],
                ],
            ],
            'arsse_folders' => [
                'columns' => [
                    'id'     => "int",
                    'owner'  => "str",
                    'parent' => "int",
                    'name'   => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", null, "Technology"],
                    [2, "john.doe@example.com",    1, "Software"],
                    [3, "john.doe@example.com",    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, "Politics"],
                    [5, "john.doe@example.com", null, "Politics"],
                    [6, "john.doe@example.com",    2, "Politics"],
                ],
            ],
            'arsse_icons' => [
                'columns' => [
                    'id'   => "int",
                    'url'  => "str",
                    'data' => "blob",
                ],
                'rows' => [
                    [1,"http://example.com/favicon.ico", "ICON DATA"],
                    [2,"http://example.net/favicon.ico", null],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'username'   => "str",
                    'password'   => "str",
                    'updated'    => "datetime",
                    'next_fetch' => "datetime",
                    'icon'       => "int",
                ],
                'rows' => [
                    [1,"http://example.com/feed1", "Ook", "", "",strtotime("now"),strtotime("now"),null],
                    [2,"http://example.com/feed2", "eek", "", "",strtotime("now - 1 hour"),strtotime("now - 1 hour"),1],
                    [3,"http://example.com/feed3", "Ack", "", "",strtotime("now + 1 hour"),strtotime("now + 1 hour"),2],
                    [4,"http://example.com/feed4", "Foo", "", "",strtotime("now + 1 hour"),strtotime("now + 1 hour"),null],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'feed'       => "int",
                    'title'      => "str",
                    'folder'     => "int",
                    'pinned'     => "bool",
                    'order_type' => "int",
                    'keep_rule'  => "str",
                    'block_rule' => "str",
                    'scrape'     => "bool",
                ],
                'rows' => [
                    [1,"john.doe@example.com",2,null,null,1,2,null,null,0],
                    [2,"jane.doe@example.com",2,null,null,0,0,null,null,0],
                    [3,"john.doe@example.com",3,"Ook",2,0,1,null,null,0],
                    [4,"jill.doe@example.com",2,null,null,0,0,null,null,0],
                    [5,"jack.doe@example.com",2,null,null,1,2,"","3|E",0],
                    [6,"john.doe@example.com",4,"Bar",3,0,0,null,null,0],
                ],
            ],
            'arsse_tags' => [
                'columns' => [
                    'id'       => "int",
                    'owner'    => "str",
                    'name'     => "str",
                ],
                'rows' => [
                    [1,"john.doe@example.com","Interesting"],
                    [2,"john.doe@example.com","Fascinating"],
                    [3,"jane.doe@example.com","Boring"],
                    [4,"john.doe@example.com","Lonely"],
                ],
            ],
            'arsse_tag_members' => [
                'columns' => [
                    'tag'          => "int",
                    'subscription' => "int",
                    'assigned'     => "bool",
                ],
                'rows' => [
                    [1,1,1],
                    [1,3,0],
                    [2,1,1],
                    [2,3,1],
                    [3,2,1],
                ],
            ],
            'arsse_articles' => [
                'columns' => [
                    'id'                 => "int",
                    'feed'               => "int",
                    'url_title_hash'     => "str",
                    'url_content_hash'   => "str",
                    'title_content_hash' => "str",
                    'title'              => "str",
                ],
                'rows' => [
                    [1,2,"","","","Title 1"],
                    [2,2,"","","","Title 2"],
                    [3,2,"","","","Title 3"],
                    [4,2,"","","","Title 4"],
                    [5,2,"","","","Title 5"],
                    [6,3,"","","","Title 6"],
                    [7,3,"","","","Title 7"],
                    [8,3,"","","","Title 8"],
                ],
            ],
            'arsse_editions' => [
                'columns' => [
                    'id'      => "int",
                    'article' => "int",
                ],
                'rows' => [
                    [1,1],
                    [2,2],
                    [3,3],
                    [4,4],
                    [5,5],
                    [6,6],
                    [7,7],
                    [8,8],
                ],
            ],
            'arsse_categories' => [
                'columns' => [
                    'article' => "int",
                    'name'    => "str",
                ],
                'rows' => [
                    [1,"A"],
                    [2,"B"],
                    [4,"D"],
                    [5,"E"],
                    [6,"F"],
                    [7,"G"],
                    [8,"H"],
                ],
            ],
            'arsse_marks' => [
                'columns' => [
                    'article'      => "int",
                    'subscription' => "int",
                    'read'         => "bool",
                    'starred'      => "bool",
                    'hidden'       => "bool",
                ],
                'rows' => [
                    [1,2,1,0,0],
                    [2,2,1,0,0],
                    [3,2,1,0,0],
                    [4,2,1,0,0],
                    [5,2,1,0,0],
                    [1,1,1,0,0],
                    [7,3,1,0,0],
                    [8,3,0,0,0],
                    [1,5,1,0,0],
                    [3,5,1,0,1],
                    [4,5,0,0,0],
                    [5,5,0,0,1],
                ],
            ],
        ];
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesSubscription(): void {
        unset($this->data, $this->user);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddASubscriptionToAnExistingFeed(): void {
        $url = "http://example.com/feed1";
        $subID = $this->nextID("arsse_subscriptions");
        Arsse::$db = \Phake::partialMock(Database::class, static::$drv);
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url));
        \Phake::verify(Arsse::$db, \Phake::never())->feedUpdate(\Phake::anyParameters());
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,1];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddASubscriptionToANewFeed(): void {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        Arsse::$db = \Phake::partialMock(Database::class, static::$drv);
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url, "", "", false));
        \Phake::verify(Arsse::$db)->feedUpdate($feedID, true, false);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$url,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,$feedID];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddASubscriptionToANewFeedViaDiscovery(): void {
        $url = "http://localhost:8000/Feed/Discovery/Valid";
        $discovered = "http://localhost:8000/Feed/Discovery/Feed";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        Arsse::$db = \Phake::partialMock(Database::class, static::$drv);
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url, "", "", true));
        \Phake::verify(Arsse::$db)->feedUpdate($feedID, true, false);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$discovered,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,$feedID];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddASubscriptionToAnInvalidFeed(): void {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        Arsse::$db = \Phake::partialMock(Database::class, static::$drv);
        \Phake::when(Arsse::$db)->feedUpdate->thenThrow(new FeedException("", ['url' => $url], $this->mockGuzzleException(ClientException::class, "", 404)));
        $this->assertException("invalidUrl", "Feed");
        try {
            Arsse::$db->subscriptionAdd($this->user, $url, "", "", false);
        } finally {
            \Phake::verify(Arsse::$db)->feedUpdate($feedID, true, false);
            $state = $this->primeExpectations($this->data, [
                'arsse_feeds'         => ['id','url','username','password'],
                'arsse_subscriptions' => ['id','owner','feed'],
            ]);
            $this->compareExpectations(static::$drv, $state);
        }
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddADuplicateSubscription(): void {
        $url = "http://example.com/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddADuplicateSubscriptionWithEquivalentUrl(): void {
        $url = "http://EXAMPLE.COM/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    //#[CoversMethod(Database::class, "subscriptionAdd")]
    //#[CoversMethod(Database::class, "feedAdd")]
    public function testAddADuplicateSubscriptionViaRedirection(): void {
        $url = "http://localhost:8000/Feed/Parsing/Valid";
        Arsse::$db->subscriptionAdd($this->user, $url);
        $subID = $this->nextID("arsse_subscriptions");
        $url = "http://localhost:8000/Feed/Fetching/RedirectionDuplicate";
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url));
    }

    //#[CoversMethod(Database::class, "subscriptionRemove")]
    public function testRemoveASubscription(): void {
        $this->assertTrue(Arsse::$db->subscriptionRemove($this->user, 1));
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        array_shift($state['arsse_subscriptions']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "subscriptionRemove")]
    public function testRemoveAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 2112);
    }

    //#[CoversMethod(Database::class, "subscriptionRemove")]
    public function testRemoveAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, -1);
    }

    //#[CoversMethod(Database::class, "subscriptionRemove")]
    public function testRemoveASubscriptionForTheWrongOwner(): void {
        $this->user = "jane.doe@example.com";
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 1);
    }

    //#[CoversMethod(Database::class, "subscriptionList")]
    //#[CoversMethod(Database::class, "subscriptionPropertiesGet")]
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

    //#[CoversMethod(Database::class, "subscriptionList")]
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

    //#[CoversMethod(Database::class, "subscriptionList")]
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

    //#[CoversMethod(Database::class, "subscriptionList")]
    public function testListSubscriptionsInAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionList($this->user, 4);
    }

    //#[CoversMethod(Database::class, "subscriptionCount")]
    public function testCountSubscriptions(): void {
        $this->assertSame(3, Arsse::$db->subscriptionCount($this->user));
        $this->assertSame(1, Arsse::$db->subscriptionCount($this->user, 2));
    }

    //#[CoversMethod(Database::class, "subscriptionCount")]
    public function testCountSubscriptionsInAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionCount($this->user, 4);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesGet")]
    public function testGetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, 2112);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesGet")]
    public function testGetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, -1);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testSetThePropertiesOfASubscription(): void {
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
            'arsse_feeds'         => ['id','url','username','password','title'],
            'arsse_subscriptions' => ['id','owner','feed','title','folder','pinned','order_type','keep_rule','block_rule','scrape'],
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,"Ook Ook",3,0,0,"ook","eek",1];
        $this->compareExpectations(static::$drv, $state);
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, [
            'title'      => null,
            'keep_rule'  => null,
            'block_rule' => null,
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,null,3,0,0,null,null,1];
        $this->compareExpectations(static::$drv, $state);
        // making no changes is a valid result
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['unhinged' => true]);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testMoveASubscriptionToAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['folder' => 4]);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testMoveASubscriptionToTheRootFolder(): void {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 3, ['folder' => null]));
    }

    #[DataProvider("provideInvalidSubscriptionProperties")]
    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testSetThePropertiesOfASubscriptionToInvalidValues(array $data, string $exp): void {
        $this->assertException($exp, "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, $data);
    }

    public static function provideInvalidSubscriptionProperties(): iterable {
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

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testRenameASubscriptionToZero(): void {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => 0]));
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testSetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 2112, ['folder' => null]);
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testSetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, -1, ['folder' => null]);
    }

    //#[CoversMethod(Database::class, "subscriptionIcon")]
    public function testRetrieveTheFaviconOfASubscription(): void {
        $exp = "http://example.com/favicon.ico";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon(null, 1)['url']);
        $this->assertSame($exp, Arsse::$db->subscriptionIcon(null, 2)['url']);
        $this->assertSame(null, Arsse::$db->subscriptionIcon(null, 6));
    }

    //#[CoversMethod(Database::class, "subscriptionIcon")]
    public function testRetrieveTheFaviconOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionIcon(null, -2112);
    }

    //#[CoversMethod(Database::class, "subscriptionIcon")]
    public function testRetrieveTheFaviconOfASubscriptionWithUser(): void {
        $exp = "http://example.com/favicon.ico";
        $user = "john.doe@example.com";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon($user, 1)['url']);
        $this->assertSame(null, Arsse::$db->subscriptionIcon($user, 6));
        $user = "jane.doe@example.com";
        $this->assertSame($exp, Arsse::$db->subscriptionIcon($user, 2)['url']);
    }

    //#[CoversMethod(Database::class, "subscriptionIcon")]
    public function testRetrieveTheFaviconOfASubscriptionOfTheWrongUser(): void {
        $user = "john.doe@example.com";
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionIcon($user, 2);
    }

    //#[CoversMethod(Database::class, "subscriptionTagsGet")]
    public function testListTheTagsOfASubscription(): void {
        $this->assertEquals([1,2], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 1));
        $this->assertEquals([2], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 3));
        $this->assertEquals(["Fascinating","Interesting"], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 1, true));
        $this->assertEquals(["Fascinating"], Arsse::$db->subscriptionTagsGet("john.doe@example.com", 3, true));
    }

    //#[CoversMethod(Database::class, "subscriptionTagsGet")]
    public function testListTheTagsOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionTagsGet($this->user, 101);
    }

    //#[CoversMethod(Database::class, "subscriptionRefreshed")]
    public function testGetRefreshTimeOfASubscription(): void {
        $user = "john.doe@example.com";
        $this->assertTime(strtotime("now + 1 hour"), Arsse::$db->subscriptionRefreshed($user));
        $this->assertTime(strtotime("now - 1 hour"), Arsse::$db->subscriptionRefreshed($user, 1));
    }

    //#[CoversMethod(Database::class, "subscriptionRefreshed")]
    public function testGetRefreshTimeOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        $this->assertTime(strtotime("now - 1 hour"), Arsse::$db->subscriptionRefreshed("john.doe@example.com", 2));
    }

    //#[CoversMethod(Database::class, "subscriptionPropertiesSet")]
    //#[CoversMethod(Database::class, "subscriptionValidateId")]
    //#[CoversMethod(Database::class, "subscriptionRulesApply")]
    public function testSetTheFilterRulesOfASubscriptionCheckingMarks(): void {
        Arsse::$db->subscriptionPropertiesSet("jack.doe@example.com", 5, ['keep_rule' => "1|B|3|D", 'block_rule' => "4"]);
        $state = $this->primeExpectations($this->data, ['arsse_marks' => ['article', 'subscription', 'hidden']]);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][10][2] = 1;
        $this->compareExpectations(static::$drv, $state);
    }
}
