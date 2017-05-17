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
                    'id'        => "int",
                    'url'       => "str",
                    'title'     => "str",
                    'username'  => "str",
                    'password'  => "str",
                ],
                'rows' => [
                    [1,"http://example.com/feed1", "Ook", "", ""],
                    [2,"http://example.com/feed2", "Eek", "", ""],
                    [3,"http://example.com/feed3", "Ack", "", ""],
                ]
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'     => "int",
                    'owner'  => "str",
                    'feed'   => "int",
                    'title'  => "str",
                    'folder' => "int",
                ],
                'rows' => [
                    [1,"john.doe@example.com",2,null,null],
                    [2,"jane.doe@example.com",2,null,null],
                    [3,"john.doe@example.com",3,"Ook",2],
                ]
            ],
            'arsse_articles' => [
                'columns' => [
                    'id' => "int",
                    'feed' => "int",
                    'url_title_hash' => "str",
                    'url_content_hash' => "str",
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
                    [1,1,"jane.doe@example.com",true,false],
                    [2,2,"jane.doe@example.com",true,false],
                    [3,3,"jane.doe@example.com",true,false],
                    [4,4,"jane.doe@example.com",true,false],
                    [5,5,"jane.doe@example.com",true,false],
                    [6,6,"jane.doe@example.com",true,false],
                    [7,7,"jane.doe@example.com",true,false],
                    [8,8,"jane.doe@example.com",true,false],
                    [9, 1,"john.doe@example.com",true,false],
                    [10,7,"john.doe@example.com",true,false],
                    [11,8,"john.doe@example.com",true,false],
                ]
            ],
        ];
        // merge tables
        $this->data = array_merge($this->data, $data);
        $this->primeDatabase($this->data);
        // initialize a partial mock of the Database object to later manipulate the feedUpdate method
        Data::$db = Phake::PartialMock(Database::class, $this->drv);
    }

    function testAddASubscriptionToAnExistingFeed() {
        $user = "john.doe@example.com";
        $url = "http://example.com/feed1";
        $subID = $this->nextID("arsse_subscriptions");
        Phake::when(Data::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Data::$db->subscriptionAdd($user, $url));
        Phake::verify(Data::$user)->authorize($user, "subscriptionAdd");
        Phake::verify(Data::$db, Phake::times(0))->feedUpdate(1, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_subscriptions']['rows'][] = [$subID,$user,1];
        $this->compareExpectations($state);
    }

    function testAddASubscriptionToANewFeed() {
        $user = "john.doe@example.com";
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        $subID = $this->nextID("arsse_subscriptions");
        Phake::when(Data::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Data::$db->subscriptionAdd($user, $url));
        Phake::verify(Data::$user)->authorize($user, "subscriptionAdd");
        Phake::verify(Data::$db)->feedUpdate($feedID, true);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        $state['arsse_feeds']['rows'][] = [$feedID,$url,"",""];
        $state['arsse_subscriptions']['rows'][] = [$subID,$user,$feedID];
        $this->compareExpectations($state);
    }

    function testAddASubscriptionToAnInvalidFeed() {
        $user = "john.doe@example.com";
        $url = "http://example.org/feed1";
        $feedID = $this->nextID("arsse_feeds");
        Phake::when(Data::$db)->feedUpdate->thenThrow(new FeedException($url, new \PicoFeed\Client\InvalidUrlException()));
        try {
            Data::$db->subscriptionAdd($user, $url);
        } catch(FeedException $e) {
            Phake::verify(Data::$user)->authorize($user, "subscriptionAdd");
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
        $user = "john.doe@example.com";
        $url = "http://example.com/feed2";
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Data::$db->subscriptionAdd($user, $url);
    }

    function testAddASubscriptionWithoutAuthority() {
        $user = "john.doe@example.com";
        $url = "http://example.com/feed1";
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionAdd($user, $url);
    }

    function testRemoveASubscription() {
        $user = "john.doe@example.com";
        $this->assertTrue(Data::$db->subscriptionRemove($user, 1));
        Phake::verify(Data::$user)->authorize($user, "subscriptionRemove");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        array_shift($state['arsse_subscriptions']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAMissingSubscription() {
        $user = "john.doe@example.com";
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionRemove($user, 2112);
    }

    function testRemoveASubscriptionForTheWrongOwner() {
        $user = "jane.doe@example.com";
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->subscriptionRemove($user, 1);
    }

    function testRemoveASubscriptionWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->subscriptionRemove("john.doe@example.com", 1);
    }

    function testListSubscriptions() {
        $user = "john.doe@example.com";
        $exp = [
            [
                'url' => "http://example.com/feed2",
                'title' => "Eek",
                'folder' => null,
                'unread' => 4,
            ],
            [
                'url' => "http://example.com/feed3",
                'title' => "Ook",
                'folder' => 2,
                'unread' => 1,
            ],
        ];
        $this->assertResult($exp, Data::$db->subscriptionList($user));
    }

    function testListSubscriptionsInAFolder() {
        $user = "john.doe@example.com";
        $exp = [
            [
                'url' => "http://example.com/feed3",
                'title' => "Ook",
                'folder' => 2,
            ],
        ];
        $this->assertResult($exp, Data::$db->subscriptionList($user, 2));
    }
}