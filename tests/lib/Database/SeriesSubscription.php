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
                ]
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'    => "int",
                    'owner' => "str",
                    'feed'  => "int",
                    'title' => "str",
                ],
                'rows' => [
                    [1,"john.doe@example.com",2,null],
                    [2,"jane.doe@example.com",2,null],
                ]
            ]
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
}