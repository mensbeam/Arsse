<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesFolder {
    protected function setUpSeriesFolder(): void {
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
                /* Layout translates to:
                Jane
                    Politics
                John
                    Technology
                        Software
                            Politics
                        Rocketry
                    Politics
                */
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
                ],
                'rows' => [
                    [1,"http://example.com/1", "Feed 1"],
                    [2,"http://example.com/2", "Feed 2"],
                    [3,"http://example.com/3", "Feed 3"],
                    [4,"http://example.com/4", "Feed 4"],
                    [5,"http://example.com/5", "Feed 5"],
                    [6,"http://example.com/6", "Feed 6"],
                    [7,"http://example.com/7", "Feed 7"],
                    [8,"http://example.com/8", "Feed 8"],
                    [9,"http://example.com/9", "Feed 9"],
                    [10,"http://example.com/10", "Feed 10"],
                    [11,"http://example.com/11", "Feed 11"],
                    [12,"http://example.com/12", "Feed 12"],
                    [13,"http://example.com/13", "Feed 13"],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'feed'       => "int",
                    'folder'     => "int",
                ],
                'rows' => [
                    [1, "john.doe@example.com",1, null],
                    [2, "john.doe@example.com",2, null],
                    [3, "john.doe@example.com",3,    1],
                    [4, "john.doe@example.com",4,    6],
                    [5, "john.doe@example.com",5,    5],
                    [6, "john.doe@example.com",10,   5],
                    [7, "jane.doe@example.com",1, null],
                    [8, "jane.doe@example.com",10,null],
                    [9, "jane.doe@example.com",2,    4],
                    [10,"jane.doe@example.com",3,    4],
                    [11,"jane.doe@example.com",4,    4],
                ],
            ],
        ];
    }

    protected function tearDownSeriesFolder(): void {
        unset($this->data);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddARootFolder(): void {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "Entertainment"]));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, null, "Entertainment"];
        $this->compareExpectations(static::$drv, $state);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddADuplicateRootFolder(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Politics"]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddANestedFolder(): void {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "GNOME", 'parent' => 2]));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, 2, "GNOME"];
        $this->compareExpectations(static::$drv, $state);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddANestedFolderToAMissingParent(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 2112]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddANestedFolderToAnInvalidParent(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => "stringFolderId"]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddANestedFolderForTheWrongOwner(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 4]); // folder ID 4 belongs to Jane
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddAFolderWithAMissingName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", []);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddAFolderWithABlankName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => ""]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderAdd
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testAddAFolderWithAWhitespaceName(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => " "]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderList
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     */
    public function testListRootFolders(): void {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null, 'children' => 0, 'feeds' => 2],
            ['id' => 1, 'name' => "Technology", 'parent' => null, 'children' => 2, 'feeds' => 1],
        ];
        $this->assertResult($exp, Arsse::$db->folderList("john.doe@example.com", null, false));
        $exp = [
            ['id' => 4, 'name' => "Politics",   'parent' => null, 'children' => 0],
        ];
        $this->assertResult($exp, Arsse::$db->folderList("jane.doe@example.com", null, false));
        $exp = [];
        $this->assertResult($exp, Arsse::$db->folderList("admin@example.net", null, false));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderList
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     */
    public function testListFoldersRecursively(): void {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null, 'children' => 0, 'feeds' => 2],
            ['id' => 6, 'name' => "Politics",   'parent' => 2,    'children' => 0, 'feeds' => 1],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1,    'children' => 0, 'feeds' => 0],
            ['id' => 2, 'name' => "Software",   'parent' => 1,    'children' => 1, 'feeds' => 0],
            ['id' => 1, 'name' => "Technology", 'parent' => null, 'children' => 2, 'feeds' => 1],
        ];
        $this->assertResult($exp, Arsse::$db->folderList("john.doe@example.com", null, true));
        $exp = [
            ['id' => 6, 'name' => "Politics",   'parent' => 2, 'children' => 0, 'feeds' => 1],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1, 'children' => 0, 'feeds' => 0],
            ['id' => 2, 'name' => "Software",   'parent' => 1, 'children' => 1, 'feeds' => 0],
        ];
        $this->assertResult($exp, Arsse::$db->folderList("john.doe@example.com", 1, true));
        $exp = [];
        $this->assertResult($exp, Arsse::$db->folderList("jane.doe@example.com", 4, true));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderList
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     */
    public function testListFoldersOfAMissingParent(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 2112);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderList
     * @covers \JKingWeb\Arsse\Database::folderValidateId
     */
    public function testListFoldersOfTheWrongOwner(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    /** @covers \JKingWeb\Arsse\Database::folderRemove */
    public function testRemoveAFolder(): void {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 6));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        array_pop($state['arsse_folders']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::folderRemove */
    public function testRemoveAFolderTree(): void {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 1));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        foreach ([0,1,2,5] as $index) {
            unset($state['arsse_folders']['rows'][$index]);
        }
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::folderRemove */
    public function testRemoveAMissingFolder(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 2112);
    }

    /** @covers \JKingWeb\Arsse\Database::folderRemove */
    public function testRemoveAnInvalidFolder(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", -1);
    }

    /** @covers \JKingWeb\Arsse\Database::folderRemove */
    public function testRemoveAFolderOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesGet */
    public function testGetThePropertiesOfAFolder(): void {
        $exp = [
            'id'     => 6,
            'name'   => "Politics",
            'parent' => 2,
        ];
        $this->assertArraySubset($exp, Arsse::$db->folderPropertiesGet("john.doe@example.com", 6));
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesGet */
    public function testGetThePropertiesOfAMissingFolder(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 2112);
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesGet */
    public function testGetThePropertiesOfAnInvalidFolder(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", -1);
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesGet */
    public function testGetThePropertiesOfAFolderOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesSet */
    public function testMakeNoChangesToAFolder(): void {
        $this->assertFalse(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, []));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testRenameAFolder(): void {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "Opinion"]));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][3] = "Opinion";
        $this->compareExpectations(static::$drv, $state);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testRenameTheRootFolder(): void {
        $this->assertFalse(Arsse::$db->folderPropertiesSet("john.doe@example.com", null, ['name' => "Opinion"]));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testRenameAFolderToTheEmptyString(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => ""]));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testRenameAFolderToWhitespaceOnly(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "   "]));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     */
    public function testRenameAFolderToAnInvalidValue(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => []]));
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveAFolder(): void {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => 5]));
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][2] = 5; // parent should have changed
        $this->compareExpectations(static::$drv, $state);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveTheRootFolder(): void {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 0, ['parent' => 1]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveAFolderToItsDescendant(): void {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 3]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveAFolderToItself(): void {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 1]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveAFolderToAMissingParent(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 2112]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testMoveAFolderToAnInvalidParent(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => "ThisFolderDoesNotExist"]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testCauseAFolderCollision(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => null]);
    }

    /**
     * @covers \JKingWeb\Arsse\Database::folderPropertiesSet
     * @covers \JKingWeb\Arsse\Database::folderValidateName
     * @covers \JKingWeb\Arsse\Database::folderValidateMove
     */
    public function testCauseACompoundFolderCollision(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 3, ['parent' => null, 'name' => "Technology"]);
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesSet */
    public function testSetThePropertiesOfAMissingFolder(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 2112, ['parent' => null]);
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesSet */
    public function testSetThePropertiesOfAnInvalidFolder(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", -1, ['parent' => null]);
    }

    /** @covers \JKingWeb\Arsse\Database::folderPropertiesSet */
    public function testSetThePropertiesOfAFolderForTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 4, ['parent' => null]); // folder ID 4 belongs to Jane
    }
}
