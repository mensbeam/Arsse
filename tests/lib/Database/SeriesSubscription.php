<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use Phake;

trait SeriesSubscription {
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
                'rights'   => 'int',
            ],
            'rows' => [
                ["jane.doe@example.com", "", "Jane Doe", UserDriver::RIGHTS_NONE],
                ["john.doe@example.com", "", "John Doe", UserDriver::RIGHTS_NONE],
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
            ]
        ],
        'arsse_feeds' => [
            'columns' => [
                'id'         => "int",
                'url'        => "str",
                'title'      => "str",
                'username'   => "str",
                'password'   => "str",
                'next_fetch' => "datetime",
            ],
            'rows' => [] // filled in the series setup
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

    function setUpSeries() {
        $this->data['arsse_feeds']['rows'] = [
            [1,"http://example.com/feed1", "Ook", "", "",strtotime("now")],
            [2,"http://example.com/feed2", "Eek", "", "",strtotime("now - 1 hour")],
            [3,"http://example.com/feed3", "Ack", "", "",strtotime("now + 1 hour")],
        ];
        // initialize a partial mock of the Database object to later manipulate the feedUpdate method
        Arsse::$db = Phake::partialMock(Database::class, $this->drv);
        $this->user = "john.doe@example.com";
    }

    function testAddASubscriptionToAnExistingFeed() {
        $url = "http://example.com/feed1";
        $subID = $this->nextID("arsse_subscriptions");
        Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Arsse::$db->subscriptionAdd($this->user, $url));
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
        Phake::verify(Arsse::$db, Phake::times(0))->feedUpdate(1, true);
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
        Phake::when(Arsse::$db)->feedUpdate->thenReturn(true);
        $this->assertSame($subID,Arsse::$db->subscriptionAdd($this->user, $url));
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
        Phake::verify(Arsse::$db)->feedUpdate($feedID, true);
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
        Phake::when(Arsse::$db)->feedUpdate->thenThrow(new FeedException($url, new \PicoFeed\Client\InvalidUrlException()));
        try {
            Arsse::$db->subscriptionAdd($this->user, $url);
        } catch(FeedException $e) {
            Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionAdd");
            Phake::verify(Arsse::$db)->feedUpdate($feedID, true);
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
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    function testAddASubscriptionWithoutAuthority() {
        $url = "http://example.com/feed1";
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionAdd($this->user, $url);
    }

    function testRemoveASubscription() {
        $this->assertTrue(Arsse::$db->subscriptionRemove($this->user, 1));
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionRemove");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password'],
            'arsse_subscriptions' => ['id','owner','feed'],
        ]);
        array_shift($state['arsse_subscriptions']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAMissingSubscription() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 2112);
    }

    function testRemoveASubscriptionForTheWrongOwner() {
        $this->user = "jane.doe@example.com";
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionRemove($this->user, 1);
    }

    function testRemoveASubscriptionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionRemove($this->user, 1);
    }

    function testListSubscriptions() {
        $exp = [
            [
                'url'        => "http://example.com/feed2",
                'title'      => "Eek",
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
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionList");
        $this->assertArraySubset($exp[0], Arsse::$db->subscriptionPropertiesGet($this->user, 1));
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionPropertiesGet");
        $this->assertArraySubset($exp[1], Arsse::$db->subscriptionPropertiesGet($this->user, 3));
    }

    function testListSubscriptionsInAFolder() {
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

    function testListSubscriptionsInAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionList($this->user, 4);
    }

    function testListSubscriptionsWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionList($this->user);
    }

    function testGetThePropertiesOfAMissingSubscription() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesGet($this->user, 2112);
    }

    function testGetThePropertiesOfASubscriptionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionPropertiesGet($this->user, 1);
    }

    function testSetThePropertiesOfASubscription() {
        Arsse::$db->subscriptionPropertiesSet($this->user, 1,[
            'title' => "Ook Ook",
            'folder' => 3,
            'pinned' => false,
            'order_type' => 0,
        ]);
        Phake::verify(Arsse::$user)->authorize($this->user, "subscriptionPropertiesSet");
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds'         => ['id','url','username','password','title'],
            'arsse_subscriptions' => ['id','owner','feed','title','folder','pinned','order_type'],
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,"Ook Ook",3,0,0];
        $this->compareExpectations($state);
        Arsse::$db->subscriptionPropertiesSet($this->user, 1,[
            'title' => null,
        ]);
        $state['arsse_subscriptions']['rows'][0] = [1,"john.doe@example.com",2,null,3,0,0];
        $this->compareExpectations($state);
    }

    function testMoveASubscriptionToAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['folder' => 4]);
    }

    function testMoveASubscriptionToTheRootFolder() {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 3, ['folder' => null]));
    }

    function testRenameASubscriptionToABlankTitle() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => ""]);
    }

    function testRenameASubscriptionToAWhitespaceTitle() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => "    "]);
    }

    function testRenameASubscriptionToFalse() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => false]);
    }

    function testRenameASubscriptionToZero() {
        $this->assertTrue(Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['title' => 0]));
    }

    function testSetThePropertiesOfAMissingSubscription() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionPropertiesSet($this->user, 2112, ['folder' => null]);
    }

    function testSetThePropertiesOfASubscriptionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->subscriptionPropertiesSet($this->user, 1, ['folder' => null]);
    }
}