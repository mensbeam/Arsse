<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;

trait SeriesTag {
    protected $checkMembers;
    protected $checkTags;

    protected function setUpSeriesTag(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "", 1],
                    ["john.doe@example.com", "", 2],
                    ["john.doe@example.org", "", 3],
                    ["john.doe@example.net", "", 4],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "feed_title", "title"],
                'rows'    => [
                    [1,  "john.doe@example.com", "http://example.com/1",  "",           "Lord of Carrots"],
                    [2,  "john.doe@example.com", "http://example.com/2",  "",           null],
                    [3,  "john.doe@example.com", "http://example.com/3",  "Feed Title", "Subscription Title"],
                    [4,  "john.doe@example.com", "http://example.com/4",  "",           null],
                    [5,  "john.doe@example.com", "http://example.com/10", "",           null],
                    [6,  "jane.doe@example.com", "http://example.com/1",  "",           null],
                    [7,  "jane.doe@example.com", "http://example.com/10", "",           null],
                    [8,  "john.doe@example.org", "http://example.com/11", "",           null],
                    [9,  "john.doe@example.org", "http://example.com/12", "",           null],
                    [10, "john.doe@example.org", "http://example.com/13", "",           null],
                    [11, "john.doe@example.net", "http://example.com/10", "",           null],
                    [12, "john.doe@example.net", "http://example.com/2",  "",           null],
                    [13, "john.doe@example.net", "http://example.com/3",  "Feed Title", null],
                    [14, "john.doe@example.net", "http://example.com/4",  "",           null],
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
                    [1,5,1],
                    [2,1,1],
                    [2,3,1],
                    [2,5,1],
                ],
            ],
        ];
        $this->checkTags = ['arsse_tags' => ["id","owner","name"]];
        $this->checkMembers = ['arsse_tag_members' => ["tag","subscription","assigned"]];
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesTag(): void {
        unset($this->data, $this->checkTags, $this->checkMembers, $this->user);
    }

    public function testAddATag(): void {
        $user = "john.doe@example.com";
        $tagID = $this->nextID("arsse_tags");
        $this->assertSame($tagID, Arsse::$db->tagAdd($user, ['name' => "Entertaining"]));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][] = [$tagID, $user, "Entertaining"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddADuplicateTag(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => "Interesting"]);
    }

    public function testAddATagWithAMissingName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", []);
    }

    public function testAddATagWithABlankName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => ""]);
    }

    public function testAddATagWithAWhitespaceName(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => " "]);
    }

    public function testListTags(): void {
        $exp = [
            ['id' => 2, 'name' => "Fascinating"],
            ['id' => 1, 'name' => "Interesting"],
            ['id' => 4, 'name' => "Lonely"],
        ];
        $this->assertResult($exp, Arsse::$db->tagList("john.doe@example.com"));
        $exp = [
            ['id' => 3, 'name' => "Boring"],
        ];
        $this->assertResult($exp, Arsse::$db->tagList("jane.doe@example.com"));
        $exp = [];
        $this->assertResult($exp, Arsse::$db->tagList("jane.doe@example.com", false));
    }

    public function testRemoveATag(): void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", 1));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveATagByName(): void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", "Interesting", true));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 2112);
    }

    public function testRemoveAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", -1);
    }

    public function testRemoveAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", [], true);
    }

    public function testRemoveATagOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    public function testGetThePropertiesOfATag(): void {
        $exp = [
            'id'       => 2,
            'name'     => "Fascinating",
        ];
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", 2));
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", "Fascinating", true));
    }

    public function testGetThePropertiesOfAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 2112);
    }

    public function testGetThePropertiesOfAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", -1);
    }

    public function testGetThePropertiesOfAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", [], true);
    }

    public function testGetThePropertiesOfATagOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    public function testMakeNoChangesToATag(): void {
        $this->assertFalse(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, []));
    }

    public function testRenameATag(): void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Curious"]));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameATagByName(): void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", "Interesting", ['name' => "Curious"], true));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameATagToTheEmptyString(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => ""]));
    }

    public function testRenameATagToWhitespaceOnly(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "   "]));
    }

    public function testRenameATagToAnInvalidValue(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => []]));
    }

    public function testCauseATagCollision(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Fascinating"]);
    }

    public function testSetThePropertiesOfAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 2112, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", -1, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", [], ['name' => "Exciting"], true);
    }

    public function testSetThePropertiesOfATagForTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 3, ['name' => "Exciting"]); // tag ID 3 belongs to Jane
    }

    public function testListTaggedSubscriptions(): void {
        $exp = [1,5];
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 1));
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", "Interesting", true));
        $exp = [1,3,5];
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 2));
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", "Fascinating", true));
        $exp = [];
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 4));
        $this->assertEquals($exp, Arsse::$db->tagSubscriptionsGet("john.doe@example.com", "Lonely", true));
    }

    public function testListTaggedSubscriptionsForAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 3);
    }

    public function testListTaggedSubscriptionsForAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", -1);
    }

    public function testApplyATagToSubscriptions(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4]);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromSubscriptions(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [1,3], Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyATagToSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [3,4], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [1,3], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyATagToNoSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromNoSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReplaceSubscriptionsOfATag(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testPurgeSubscriptionsOfATag(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testSummarizeTags(): void {
        $exp = [
            ['id' => 1, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 1, 'name' => "Interesting", 'subscription' => 5],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 3],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 5],
        ];
        $this->assertResult($exp, Arsse::$db->tagSummarize("john.doe@example.com"));
    }
}
