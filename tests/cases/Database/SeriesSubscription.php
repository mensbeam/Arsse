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
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
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
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'username'   => "str",
                    'password'   => "str",
                    'updated'    => "datetime",
                    'next_fetch' => "datetime",
                    'favicon'    => "str",
                ],
                'rows' => [], // filled in the series setup
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
                ],
                'rows' => [
                    [1,"john.doe@example.com",2,null,null,1,2],
                    [2,"jane.doe@example.com",2,null,null,0,0],
                    [3,"john.doe@example.com",3,"Ook",2,0,1],
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
                ],
                'rows' => [
                    [1,2,"","",""],
                    [2,2,"","",""],
                    [3,2,"","",""],
                    [4,2,"","",""],
                    [5,2,"","",""],
                    [6,3,"","",""],
                    [7,3,"","",""],
                    [8,3,"","",""],
                ],
            ],
            'arsse_marks' => [
                'columns' => [
                    'article'      => "int",
                    'subscription' => "int",
                    'read'         => "bool",
                    'starred'      => "bool",
                ],
                'rows' => [
                    [1,2,1,0],
                    [2,2,1,0],
                    [3,2,1,0],
                    [4,2,1,0],
                    [5,2,1,0],
                    [1,1,1,0],
                    [7,3,1,0],
                    [8,3,0,0],
                ],
            ],
        ];
        $this->data['arsse_feeds']['rows'] = [
            [1,"http://example.com/feed1", "Ook", "", "",strtotime("now"),strtotime("now"),''],
            [2,"http://example.com/feed2", "eek", "", "",strtotime("now - 1 hour"),strtotime("now - 1 hour"),'http://example.com/favicon.ico'],
            [3,"http://example.com/feed3", "Ack", "", "",strtotime("now + 1 hour"),strtotime("now + 1 hour"),''],
        ];
        // initialize a partial mock of the Database object to later manipulate the feedUpdate method
        Arsse::$db = \Phake::partialMock(Database::class, static::$drv);
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesSubscription(): void {
        unset($this->data, $this->user);
    }

    public function testAddASubscriptionToAnExistingFeed(): void {
        $url = "http://example.com/feed1";
        $subID = $this->nextID("arsse_subscriptions");
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
        \Phake::verify(Arsse::$db, \Phake::times(0))->feedUpdate(1, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddASubscriptionToANewFeed(): void {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url, "", "", false));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
        \Phake::verify(Arsse::$db)->feedUpdate($feedID, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$url,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,$feedID];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddASubscriptionToANewFeedViaDiscovery(): void {
        $url = "http://localhost:8000/Feed/Discovery/Valid";
        $discovered = "http://localhost:8000/Feed/Discovery/Feed";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        \Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url, "", "", true));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
        \Phake::verify(Arsse::$db)->feedUpdate($feedID, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$discovered,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,$feedID];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddASubscriptionToAnInvalidFeed(): void {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        \Phake::when(Arsse::$db)->feedUpdate->thenThrow(new FeedException($url, $this->mockGuzzleException(ClientException::class, "", 404)));
        $this->assertException("invalidUrl", "Feed");
        try {
            Arsse::$db->subscriptionAdd($this->user, $url, "", "", false);
        } finally {
            \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
            \Phake::verify(Arsse::$db)->feedUpdate($feedID, true);
            $state = $this->primeExpectations($this->data, [
                'arsse_feeds'         => ['id','url','username','password'],
                'arsse_subscriptions' => ['id','owner','feed'],
            ]);
            $this->compareExpectations(static::$drv, $state);
        }
    }

    public function testAddADuplicateSubscription(): void {
        $url = "http://example.com/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    public function testAddADuplicateSubscriptionWithEquivalentUrl(): void {
        $url = "http://EXAMPLE.COM/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    public function testAddADuplicateSubscriptionViaRedirection(): void {
        $url = "http://localhost:8000/Feed/Parsing/Valid";
        Arsse::$db->subscriptionAdd($this->user, $url);
        $subID = $this->nextID("arsse_subscriptions");
        $url = "http://localhost:8000/Feed/Fetching/RedirectionDuplicate";
        $this->assertSame($subID, Arsse::$db->subscriptionAdd($this->user, $url));
    }

    public function testAddASubscriptionWithoutAuthority(): void {
        $url = "http://example.com/feed1";
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    public function testRemoveASubscription(): void {
        $this->assertTrue(Arsse::$db->subscriptionRemove($this->user, 1));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionRemove");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        array_shift($state['arsse_subscriptions']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 2112);
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

    public function testRemoveASubscriptionWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionRemove($this->user, 1);
    }

    public function testListSubscriptions(): void {
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
        $this->assertResult($exp, Arsse::$db->subscriptionList($this->user));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionList");
        $this->assertArraySubset($exp[0], Arsse::$db->subscriptionPropertiesGet($this->user, 1));
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionPropertiesGet");
        $this->assertArraySubset($exp[1], Arsse::$db->subscriptionPropertiesGet($this->user, 3));
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

    public function testListSubscriptionsWithoutRecursion(): void {
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

    public function testListSubscriptionsWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionList($this->user);
    }

    public function testCountSubscriptions(): void {
        $this->assertSame(2, Arsse::$db->subscriptionCount($this->user));
        $this->assertSame(1, Arsse::$db->subscriptionCount($this->user, 2));
    }

    public function testCountSubscriptionsInAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionCount($this->user, 4);
    }

    public function testCountSubscriptionsWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionCount($this->user);
    }

    public function testGetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, 2112);
    }

    public function testGetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, -1);
    }

    public function testGetThePropertiesOfASubscriptionWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionPropertiesGet($this->user, 1);
    }

    public function testSetThePropertiesOfASubscription(): void {
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, [
            'title'      => "Ook Ook",
            'folder'     => 3,
            'pinned'     => false,
            'order_type' => 0,
        ]);
        \Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionPropertiesSet");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password','title'],
            'arsse_subscriptions' => ['id','owner','feed','title','folder','pinned','order_type'],
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,"Ook Ook",3,0,0];
        $this->compareExpectations(static::$drv, $state);
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, [
            'title' => null,
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,null,3,0,0];
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

    public function testRenameASubscriptionToABlankTitle(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => ""]);
    }

    public function testRenameASubscriptionToAWhitespaceTitle(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => "    "]);
    }

    public function testRenameASubscriptionToFalse(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => false]);
    }

    public function testRenameASubscriptionToZero(): void {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => 0]));
    }

    public function testRenameASubscriptionToAnArray(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => []]);
    }

    public function testSetThePropertiesOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 2112, ['folder' => null]);
    }

    public function testSetThePropertiesOfAnInvalidSubscription(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, -1, ['folder' => null]);
    }

    public function testSetThePropertiesOfASubscriptionWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['folder' => null]);
    }

    public function testRetrieveTheFaviconOfASubscription(): void {
        $exp = "http://example.com/favicon.ico";
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(1));
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(2));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(3));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(4));
        // authorization shouldn't have any bearing on this function
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(1));
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(2));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(3));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(4));
        // invalid IDs should simply return an empty string
        $this->assertSame('', Arsse::$db->subscriptionFavicon(-2112));
    }

    public function testRetrieveTheFaviconOfASubscriptionWithUser(): void {
        $exp = "http://example.com/favicon.ico";
        $user = "john.doe@example.com";
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(1, $user));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(2, $user));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(3, $user));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(4, $user));
        $user = "jane.doe@example.com";
        $this->assertSame('', Arsse::$db->subscriptionFavicon(1, $user));
        $this->assertSame($exp, Arsse::$db->subscriptionFavicon(2, $user));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(3, $user));
        $this->assertSame('', Arsse::$db->subscriptionFavicon(4, $user));
    }

    public function testRetrieveTheFaviconOfASubscriptionWithUserWithoutAuthority(): void {
        $exp = "http://example.com/favicon.ico";
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionFavicon(-2112, $user);
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

    public function testListTheTagsOfASubscriptionWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionTagsGet("john.doe@example.com", 1);
    }

    public function testGetRefreshTimeOfASubscription(): void {
        $user = "john.doe@example.com";
        $this->assertTime(strtotime("now + 1 hour"), Arsse::$db->subscriptionRefreshed($user));
        $this->assertTime(strtotime("now - 1 hour"), Arsse::$db->subscriptionRefreshed($user, 1));
    }

    public function testGetRefreshTimeOfAMissingSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        $this->assertTime(strtotime("now - 1 hour"), Arsse::$db->subscriptionRefreshed("john.doe@example.com", 2));
    }

    public function testGetRefreshTimeOfASubscriptionWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        $this->assertTime(strtotime("now + 1 hour"), Arsse::$db->subscriptionRefreshed("john.doe@example.com"));
    }
}
