<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversMethod;

trait SeriesTag {
    protected $checkMembers;
    protected $checkTags;

    protected function setUpSeriesTag(): void {
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
                    ["john.doe@example.org", "",3],
                    ["john.doe@example.net", "",4],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                ],
                'rows' => [
                    [1,"http://example.com/1",""],
                    [2,"http://example.com/2",""],
                    [3,"http://example.com/3","Feed Title"],
                    [4,"http://example.com/4",""],
                    [5,"http://example.com/5","Feed Title"],
                    [6,"http://example.com/6",""],
                    [7,"http://example.com/7",""],
                    [8,"http://example.com/8",""],
                    [9,"http://example.com/9",""],
                    [10,"http://example.com/10",""],
                    [11,"http://example.com/11",""],
                    [12,"http://example.com/12",""],
                    [13,"http://example.com/13",""],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'feed'       => "int",
                    'title'      => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", 1,"Lord of Carrots"],
                    [2, "john.doe@example.com", 2,null],
                    [3, "john.doe@example.com", 3,"Subscription Title"],
                    [4, "john.doe@example.com", 4,null],
                    [5, "john.doe@example.com",10,null],
                    [6, "jane.doe@example.com", 1,null],
                    [7, "jane.doe@example.com",10,null],
                    [8, "john.doe@example.org",11,null],
                    [9, "john.doe@example.org",12,null],
                    [10,"john.doe@example.org",13,null],
                    [11,"john.doe@example.net",10,null],
                    [12,"john.doe@example.net", 2,null],
                    [13,"john.doe@example.net", 3,null],
                    [14,"john.doe@example.net", 4,null],
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

    #[CoversMethod(Database::class, "tagAdd")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testAddATag(): void {
        $user = "john.doe@example.com";
        $tagID = $this->nextID("arsse_tags");
        $this->assertSame($tagID, Arsse::$db->tagAdd($user, ['name' => "Entertaining"]));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][] = [$tagID, $user, "Entertaining"];
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagAdd")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testAddADuplicateTag(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => "Interesting"]);
    }

    #[CoversMethod(Database::class, "tagAdd")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testAddATagWithAMissingName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", []);
    }

    #[CoversMethod(Database::class, "tagAdd")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testAddATagWithABlankName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => ""]);
    }

    #[CoversMethod(Database::class, "tagAdd")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testAddATagWithAWhitespaceName(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => " "]);
    }

    #[CoversMethod(Database::class, "tagList")]
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

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveATag(): void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", 1));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveATagByName(): void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", "Interesting", true));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 2112);
    }

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", -1);
    }

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", [], true);
    }

    #[CoversMethod(Database::class, "tagRemove")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testRemoveATagOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    #[CoversMethod(Database::class, "tagPropertiesGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testGetThePropertiesOfATag(): void {
        $exp = [
            'id'       => 2,
            'name'     => "Fascinating",
        ];
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", 2));
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", "Fascinating", true));
    }

    #[CoversMethod(Database::class, "tagPropertiesGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testGetThePropertiesOfAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 2112);
    }

    #[CoversMethod(Database::class, "tagPropertiesGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testGetThePropertiesOfAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", -1);
    }

    #[CoversMethod(Database::class, "tagPropertiesGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testGetThePropertiesOfAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", [], true);
    }

    #[CoversMethod(Database::class, "tagPropertiesGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testGetThePropertiesOfATagOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testMakeNoChangesToATag(): void {
        $this->assertFalse(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, []));
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testRenameATag(): void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Curious"]));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testRenameATagByName(): void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", "Interesting", ['name' => "Curious"], true));
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testRenameATagToTheEmptyString(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => ""]));
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testRenameATagToWhitespaceOnly(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "   "]));
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testRenameATagToAnInvalidValue(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => []]));
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testCauseATagCollision(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Fascinating"]);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testSetThePropertiesOfAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 2112, ['name' => "Exciting"]);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testSetThePropertiesOfAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", -1, ['name' => "Exciting"]);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testSetThePropertiesOfAnInvalidTagByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", [], ['name' => "Exciting"], true);
    }

    #[CoversMethod(Database::class, "tagPropertiesSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    #[CoversMethod(Database::class, "tagValidateName")]
    public function testSetThePropertiesOfATagForTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 3, ['name' => "Exciting"]); // tag ID 3 belongs to Jane
    }

    #[CoversMethod(Database::class, "tagSubscriptionsGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
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

    #[CoversMethod(Database::class, "tagSubscriptionsGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testListTaggedSubscriptionsForAMissingTag(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 3);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsGet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testListTaggedSubscriptionsForAnInvalidTag(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", -1);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testApplyATagToSubscriptions(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4]);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testClearATagFromSubscriptions(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [1,3], Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testApplyATagToSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [3,4], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testClearATagFromSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [1,3], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testApplyATagToNoSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testClearATagFromNoSubscriptionsByName(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testReplaceSubscriptionsOfATag(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSubscriptionsSet")]
    #[CoversMethod(Database::class, "tagValidateId")]
    public function testPurgeSubscriptionsOfATag(): void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    #[CoversMethod(Database::class, "tagSummarize")]
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
