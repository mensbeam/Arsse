<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\NextcloudNews\V1_3;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(V1_3::class)]
class TestV1_3 extends TestV1_2 {
    protected $prefix = "/index.php/apps/news/api/v1-3";

    public function setUp(): void {
        parent::setUp();
        //initialize a handler
        $this->h = new V1_3();
    }

    public function testRetrieveServerVersion(): void {
        $exp = HTTP::respJson([
            'version'       => V1_3::VERSION,
            'arsse_version' => Arsse::VERSION,
            ]);
        $this->assertMessage($exp, $this->req("GET", "/version"));
    }

    public function testMoveASubscription(): void {
        $in = [
            ['folderId' =>    0],
            ['folderId' => 42],
            ['folderId' => 2112],
            ['folderId' => 42],
            ['folderId' => -1],
            [],
        ];
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, ['folder' =>   42])->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, ['folder' => null])->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, ['folder' => 2112])->thenThrow(new ExceptionInput("idMissing")); // folder does not exist
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, ['folder' =>   -1])->thenThrow(new ExceptionInput("typeViolation")); // folder is invalid
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 42, $this->anything())->thenThrow(new ExceptionInput("subjectMissing")); // subscription does not exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/move", json_encode($in[0])));
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/move", json_encode($in[1])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/move", json_encode($in[2])));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("POST", "/feeds/42/move", json_encode($in[3])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/move", json_encode($in[4])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/move", json_encode($in[5])));
    }

    public function testRenameASubscription(): void {
        $in = [
            ['feedTitle' => null],
            ['feedTitle' => "Ook"],
            ['feedTitle' => "   "],
            ['feedTitle' => ""],
            ['feedTitle' => false],
            ['feedTitle' => "Feed does not exist"],
            [],
        ];
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, $this->identicalTo(['title' =>  null]))->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, $this->identicalTo(['title' => "Ook"]))->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, $this->identicalTo(['title' => "   "]))->thenThrow(new ExceptionInput("whitespace"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, $this->identicalTo(['title' =>    ""]))->thenThrow(new ExceptionInput("missing"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 1, $this->identicalTo(['title' => false]))->thenThrow(new ExceptionInput("missing"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet($this->userId, 42, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/rename", json_encode($in[0])));
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/rename", json_encode($in[1])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/rename", json_encode($in[2])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/rename", json_encode($in[3])));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("POST", "/feeds/42/rename", json_encode($in[4])));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/rename", json_encode($in[6])));
    }

    public function testMarkAFolderRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->folder(1)->editionRange(null, 2112)->hidden(false)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->folder(42)->editionRange(null, 2112)->hidden(false)))->thenThrow(new ExceptionInput("idMissing")); // folder doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/folders/1/read", $in));
        $this->assertMessage($exp, $this->req("POST", "/folders/1/read?newestItemId=2112"));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/folders/1/read"));
        $this->assertMessage($exp, $this->req("POST", "/folders/1/read?newestItemId=ook"));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("POST", "/folders/42/read", $in));
    }

    public function testMarkASubscriptionRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->subscription(1)->editionRange(null, 2112)->hidden(false)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->subscription(42)->editionRange(null, 2112)->hidden(false)))->thenThrow(new ExceptionInput("idMissing")); // subscription doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/read", $in));
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/read?newestItemId=2112"));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/read"));
        $this->assertMessage($exp, $this->req("POST", "/feeds/1/read?newestItemId=ook"));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("POST", "/feeds/42/read", $in));
    }

    public function testMarkAllItemsRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->editionRange(null, 2112)))->thenReturn(42);
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/items/read", $in));
        $this->assertMessage($exp, $this->req("POST", "/items/read?newestItemId=2112"));
        $exp = HTTP::respEmpty(422);
        $this->assertMessage($exp, $this->req("POST", "/items/read"));
        $this->assertMessage($exp, $this->req("POST", "/items/read?newestItemId=ook"));
    }

    public function testChangeMarksOfASingleArticle(): void {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->edition(1)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->edition(42)))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unread, $this->equalTo((new Context)->edition(2)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unread, $this->equalTo((new Context)->edition(47)))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $star, $this->equalTo((new Context)->edition(3)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $star, $this->equalTo((new Context)->edition(2112)))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unstar, $this->equalTo((new Context)->edition(4)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unstar, $this->equalTo((new Context)->edition(1337)))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/items/1/read"));
        $this->assertMessage($exp, $this->req("POST", "/items/2/unread"));
        $this->assertMessage($exp, $this->req("POST", "/items/3/star"));
        $this->assertMessage($exp, $this->req("POST", "/items/4/unstar"));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req("POST", "/items/42/read"));
        $this->assertMessage($exp, $this->req("POST", "/items/47/unread"));
        $this->assertMessage($exp, $this->req("POST", "/items/2112/star"));
        $this->assertMessage($exp, $this->req("POST", "/items/1337/unstar"));
        \Phake::verify(Arsse::$db, \Phake::times(8))->articleMark($this->userId, \Phake::ignoreRemaining());
    }

    public function testChangeMarksOfMultipleArticles(): void {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        $in = [
            ["ook","eek","ack"],
            range(100, 199),
        ];
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->anything())->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->equalTo((new Context)->editions([])))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->equalTo((new Context)->articles([])))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("POST", "/items/read/multiple"));
        $this->assertMessage($exp, $this->req("POST", "/items/unread/multiple"));
        $this->assertMessage($exp, $this->req("POST", "/items/star/multiple"));
        $this->assertMessage($exp, $this->req("POST", "/items/unstar/multiple"));
        $this->assertMessage($exp, $this->req("POST", "/items/read/multiple", json_encode(['itemIds' => "ook"])));
        $this->assertMessage($exp, $this->req("POST", "/items/unread/multiple", json_encode(['itemIds' => "ook"])));
        $this->assertMessage($exp, $this->req("POST", "/items/star/multiple", json_encode(['itemIds' => "ook"])));
        $this->assertMessage($exp, $this->req("POST", "/items/unstar/multiple", json_encode(['itemIds' => "ook"])));
        $this->assertMessage($exp, $this->req("POST", "/items/read/multiple", json_encode(['itemIds' => []])));
        $this->assertMessage($exp, $this->req("POST", "/items/unread/multiple", json_encode(['itemIds' => []])));
        $this->assertMessage($exp, $this->req("POST", "/items/read/multiple", json_encode(['itemIds' => $in[0]])));
        $this->assertMessage($exp, $this->req("POST", "/items/unread/multiple", json_encode(['itemIds' => $in[0]])));
        $this->assertMessage($exp, $this->req("POST", "/items/read/multiple", json_encode(['itemIds' => $in[1]])));
        $this->assertMessage($exp, $this->req("POST", "/items/unread/multiple", json_encode(['itemIds' => $in[1]])));
        $this->assertMessage($exp, $this->req("POST", "/items/star/multiple", json_encode(['itemIds' => []])));
        $this->assertMessage($exp, $this->req("POST", "/items/unstar/multiple", json_encode(['itemIds' => []])));
        $this->assertMessage($exp, $this->req("POST", "/items/star/multiple", json_encode(['itemIds' => $in[0]])));
        $this->assertMessage($exp, $this->req("POST", "/items/unstar/multiple", json_encode(['itemIds' => $in[0]])));
        $this->assertMessage($exp, $this->req("POST", "/items/star/multiple", json_encode(['itemIds' => $in[1]])));
        $this->assertMessage($exp, $this->req("POST", "/items/unstar/multiple", json_encode(['itemIds' => $in[1]])));
        // ensure the data model was queried appropriately for read/unread
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions($in[1])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions($in[1])));
        // ensure the data model was queried appropriately for star/unstar
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->editions($in[1])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->editions($in[1])));
    }

    public function testQueryTheServerStatus(): void {
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        \Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        \Phake::when(Arsse::$db)->driverCharsetAcceptable->thenReturn(true)->thenReturn(false);
        $arr1 = $arr2 = [
            'version'       => V1_3::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings'      => [
                'improperlyConfiguredCron' => false,
                'incorrectDbCharset'       => false,
            ],
        ];
        $arr2['warnings']['improperlyConfiguredCron'] = true;
        $arr2['warnings']['incorrectDbCharset'] = true;
        $exp = HTTP::respJson($arr1);
        $this->assertMessage($exp, $this->req("GET", "/status"));
    }
}
