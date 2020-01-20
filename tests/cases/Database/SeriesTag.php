<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;

trait SeriesTag {
    protected function setUpSeriesTag():void {
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                ],
                'rows' => [
                    ["jane.doe@example.com", ""],
                    ["john.doe@example.com", ""],
                    ["john.doe@example.org", ""],
                    ["john.doe@example.net", ""],
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
                ]
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
                ]
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
                    'tag' => "int",
                    'subscription' => "int",
                    'assigned' => "bool",
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

    protected function tearDownSeriesTag():void {
        unset($this->data, $this->checkTags, $this->checkMembers, $this->user);
    }

    public function testAddATag():void {
        $user = "john.doe@example.com";
        $tagID = $this->nextID("arsse_tags");
        $this->assertSame($tagID, Arsse::$db->tagAdd($user, ['name' => "Entertaining"]));
        \Phake::verify(Arsse::$user)->authorize($user, "tagAdd");
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][] = [$tagID, $user, "Entertaining"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddADuplicateTag():void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => "Interesting"]);
    }

    public function testAddATagWithAMissingName():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", []);
    }

    public function testAddATagWithABlankName():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => ""]);
    }

    public function testAddATagWithAWhitespaceName():void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => " "]);
    }

    public function testAddATagWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagAdd("john.doe@example.com", ['name' => "Boring"]);
    }

    public function testListTags():void {
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
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "tagList");
    }

    public function testListTagsWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagList("john.doe@example.com");
    }

    public function testRemoveATag():void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", 1));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "tagRemove");
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveATagByName():void {
        $this->assertTrue(Arsse::$db->tagRemove("john.doe@example.com", "Interesting", true));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "tagRemove");
        $state = $this->primeExpectations($this->data, $this->checkTags);
        array_shift($state['arsse_tags']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingTag():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 2112);
    }

    public function testRemoveAnInvalidTag():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", -1);
    }

    public function testRemoveAnInvalidTagByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", [], true);
    }

    public function testRemoveATagOfTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagRemove("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    public function testRemoveATagWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagRemove("john.doe@example.com", 1);
    }

    public function testGetThePropertiesOfATag():void {
        $exp = [
            'id'       => 2,
            'name'     => "Fascinating",
        ];
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", 2));
        $this->assertArraySubset($exp, Arsse::$db->tagPropertiesGet("john.doe@example.com", "Fascinating", true));
        \Phake::verify(Arsse::$user, \Phake::times(2))->authorize("john.doe@example.com", "tagPropertiesGet");
    }

    public function testGetThePropertiesOfAMissingTag():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 2112);
    }

    public function testGetThePropertiesOfAnInvalidTag():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", -1);
    }

    public function testGetThePropertiesOfAnInvalidTagByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", [], true);
    }

    public function testGetThePropertiesOfATagOfTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 3); // tag ID 3 belongs to Jane
    }

    public function testGetThePropertiesOfATagWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagPropertiesGet("john.doe@example.com", 1);
    }

    public function testMakeNoChangesToATag():void {
        $this->assertFalse(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, []));
    }

    public function testRenameATag():void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Curious"]));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "tagPropertiesSet");
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameATagByName():void {
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", "Interesting", ['name' => "Curious"], true));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "tagPropertiesSet");
        $state = $this->primeExpectations($this->data, $this->checkTags);
        $state['arsse_tags']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameATagToTheEmptyString():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => ""]));
    }

    public function testRenameATagToWhitespaceOnly():void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "   "]));
    }

    public function testRenameATagToAnInvalidValue():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => []]));
    }

    public function testCauseATagCollision():void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Fascinating"]);
    }

    public function testSetThePropertiesOfAMissingTag():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 2112, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidTag():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", -1, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidTagByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", [], ['name' => "Exciting"], true);
    }

    public function testSetThePropertiesOfATagForTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 3, ['name' => "Exciting"]); // tag ID 3 belongs to Jane
    }

    public function testSetThePropertiesOfATagWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagPropertiesSet("john.doe@example.com", 1, ['name' => "Exciting"]);
    }

    public function testListTaggedSubscriptions():void {
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

    public function testListTaggedSubscriptionsForAMissingTag():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 3);
    }

    public function testListTaggedSubscriptionsForAnInvalidTag():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", -1);
    }

    public function testListTaggedSubscriptionsWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagSubscriptionsGet("john.doe@example.com", 1);
    }

    public function testApplyATagToSubscriptions():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4]);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromSubscriptions():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [1,3], Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyATagToSubscriptionsByName():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [3,4], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromSubscriptionsByName():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [1,3], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyATagToNoSubscriptionsByName():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearATagFromNoSubscriptionsByName():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", "Interesting", [], Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReplaceSubscriptionsOfATag():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][1][2] = 1;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $state['arsse_tag_members']['rows'][] = [1,4,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testPurgeSubscriptionsOfATag():void {
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [], Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_tag_members']['rows'][0][2] = 0;
        $state['arsse_tag_members']['rows'][2][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyATagToSubscriptionsWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagSubscriptionsSet("john.doe@example.com", 1, [3,4]);
    }

    public function testSummarizeTags():void {
        $exp = [
            ['id' => 1, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 1, 'name' => "Interesting", 'subscription' => 5],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 3],
            ['id' => 2, 'name' => "Fascinating", 'subscription' => 5],
        ];
        $this->assertResult($exp, Arsse::$db->tagSummarize("john.doe@example.com"));
    }

    public function testSummarizeTagsWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->tagSummarize("john.doe@example.com");
    }
}
