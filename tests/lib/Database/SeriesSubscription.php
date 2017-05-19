<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use Phake;

trait SeriesSubscription {
    function setUpSeries() {
        $data = [
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'username'   => "str",
                    'password'   => "str",
                    'next_fetch' => "datetime",
                ],
                'rows' => [
                    [1,"http://example.com/feed1", "Ook", "", "",strtotime("now")],
                    [2,"http://example.com/feed2", "Eek", "", "",strtotime("now - 1 hour")],
                    [3,"http://example.com/feed3", "Ack", "", "",strtotime("now + 1 hour")],
                ]
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
                ]
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
                ]
            ],
            'arsse_marks' => [
                'columns' => [
                    'id'      => "int",
                    'article' => "int",
                    'owner'   => "str",
                    'read'    => "bool",
                    'starred' => "bool",
                ],
                'rows' => [
                    [1,1,"jane.doe@example.com",1,0],
                    [2,2,"jane.doe@example.com",1,0],
                    [3,3,"jane.doe@example.com",1,0],
                    [4,4,"jane.doe@example.com",1,0],
                    [5,5,"jane.doe@example.com",1,0],
                    [6,6,"jane.doe@example.com",1,0],
                    [7,7,"jane.doe@example.com",1,0],
                    [8,8,"jane.doe@example.com",1,0],
                    [9, 1,"john.doe@example.com",1,0],
                    [10,7,"john.doe@example.com",1,0],
                    [11,8,"john.doe@example.com",0,0],
                ]
            ],
        ];
        // merge tables
        $this->data = array_merge($this->data, $data);
        $this->primeDatabase($this->data);
        // initialize a partial mock of the Database object to later manipulate the feedUpdate method
        Data::$db = Phake::PartialMock(Database::class, $this->drv);
        $this->user = "john.doe@example.com";
    }

    function testAddASubscriptionToAnExistingFeed() {
        $url = "http://example.com/feed1";
        $subID = $this->nextID("arsse_subscriptions");
        Phake::when(Data::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Data::$db->subscriptionAdd($this->user, $url));
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionAdd");
        Phake::verify(Data::$db, Phake::times(0))->feedUpdate(1, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,1];
        $this->compareExpectations($state);
    }

    function testAddASubscriptionToANewFeed() {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        Phake::when(Data::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Data::$db->subscriptionAdd($this->user, $url));
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionAdd");
        Phake::verify(Data::$db)->feedUpdate($feedID, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$url,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$this->user,$feedID];
        $this->compareExpectations($state);
    }

    function testAddASubscriptionToAnInvalidFeed() {
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        Phake::when(Data::$db)->feedUpdate->thenThrow(new FeedException($url, new \PicoFeed\Client\InvalidUrlException()));
        try {
            Data::$db->subscriptionAdd($this->user, $url);
        } catch(FeedException $e) {
            Phake::verify(Data::$user)->authorize($this->user, "subscriptionAdd");
            Phake::verify(Data::$db)->feedUpdate($feedID, true);
            $state = $this->primeExpectations($this->data, [
                'arsse_feeds'         => ['id','url','username','password'],
                'arsse_subscriptions' => ['id','owner','feed'],
            ]);
            $this->compareExpectations($state);
            $this->assertException("invalidUrl", "Feed");
            throw $e;
        }
    }

    function testAddADuplicateSubscription() {
        $url = "http://example.com/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Data::$db->subscriptionAdd($this->user, $url);
    }

    function testAddASubscriptionWithoutAuthority() {
        $url = "http://example.com/feed1";
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionAdd($this->user, $url);
    }

    function testRemoveASubscription() {
        $this->assertTrue(Data::$db->subscriptionRemove($this->user, 1));
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionRemove");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        array_shift($state['arsse_subscriptions']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionRemove($this->user, 2112);
    }

    function testRemoveASubscriptionForTheWrongOwner() {
        $this->user = "jane.doe@example.com";
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionRemove($this->user, 1);
    }

    function testRemoveASubscriptionWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionRemove($this->user, 1);
    }

    function testListSubscriptions() {
        $exp = [
            [
                'url'        => "http://example.com/feed2",
                'title'      => "Eek",
                'folder'     => null,
                'unread'     => 4,
                'pinned'     => 1,
                'order_type' => 2,
            ],
            [
                'url'        => "http://example.com/feed3",
                'title'      => "Ook",
                'folder'     => 2,
                'unread'     => 2,
                'pinned'     => 0,
                'order_type' => 1,
            ],
        ];
        $this->assertResult($exp, Data::$db->subscriptionList($this->user));
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionList");
        $this->assertArraySubset($exp[0], Data::$db->subscriptionPropertiesGet($this->user, 1));
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionPropertiesGet");
        $this->assertArraySubset($exp[1], Data::$db->subscriptionPropertiesGet($this->user, 3));
    }

    function testListSubscriptionsInAFolder() {
        $exp = [
            [
                'url'        => "http://example.com/feed3",
                'title'      => "Ook",
                'folder'     => 2,
                'unread'     => 2,
                'pinned'     => 0,
                'order_type' => 1,
            ],
        ];
        $this->assertResult($exp, Data::$db->subscriptionList($this->user, 2));
    }

    function testListSubscriptionsWithDifferentDateFormats() {
        Data::$db->dateFormatDefault("iso8601");
        $d1 = Data::$db->subscriptionList($this->user, 2)->getRow()['added'];
        Data::$db->dateFormatDefault("http");
        $d2 = Data::$db->subscriptionList($this->user, 2)->getRow()['added'];
        $this->assertNotEquals($d1, $d2);
    }

    function testListSubscriptionsInAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionList($this->user, 4);
    }

    function testListSubscriptionsWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionList($this->user);
    }

    function testSetThePropertiesOfASubscription() {
        Data::$db->subscriptionPropertiesSet($this->user, 1,[
            'title' => "Ook Ook",
            'folder' => 3,
            'pinned' => false,
            'order_type' => 0,
        ]);
        Phake::verify(Data::$user)->authorize($this->user, "subscriptionPropertiesSet");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password','title'],
            'arsse_subscriptions' => ['id','owner','feed','title','folder','pinned','order_type'],
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,"Ook Ook",3,0,0];
        $this->compareExpectations($state);
        Data::$db->subscriptionPropertiesSet($this->user, 1,[
            'title' => "          ",
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,null,3,0,0];
        $this->compareExpectations($state);
    }

    function testMoveSubscriptionToAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionPropertiesSet($this->user, 1,[
            'folder' => 4,
        ]);
    }

    function testSetThePropertiesOfAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionPropertiesSet($this->user, 2112,[
            'folder' => null,
        ]);
    }

    function testSetThePropertiesOfASubscriptionWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionPropertiesSet($this->user, 1,[
            'folder' => null,
        ]);
    }
}