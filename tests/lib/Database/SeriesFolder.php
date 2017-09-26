<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;

use JKingWeb\Arsse\Arsse;
use Phake;

trait SeriesFolder {
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
            ],
            'rows' => [
                ["jane.doe@example.com", "", "Jane Doe"],
                ["john.doe@example.com", "", "John Doe"],
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
            ]
        ],
    ];

    public function testAddARootFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "Entertainment"]));
        Phake::verify(Arsse::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, null, "Entertainment"];
        $this->compareExpectations($state);
    }

    public function testAddADuplicateRootFolder() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Politics"]);
    }

    public function testAddANestedFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "GNOME", 'parent' => 2]));
        Phake::verify(Arsse::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, 2, "GNOME"];
        $this->compareExpectations($state);
    }

    public function testAddANestedFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 2112]);
    }

    public function testAddANestedFolderToAnInvalidParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => "stringFolderId"]);
    }

    public function testAddANestedFolderForTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 4]); // folder ID 4 belongs to Jane
    }

    public function testAddAFolderWithAMissingName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", []);
    }

    public function testAddAFolderWithABlankName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => ""]);
    }

    public function testAddAFolderWithAWhitespaceName() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => " "]);
    }

    public function testAddAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology"]);
    }

    public function testListRootFolders() {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null],
            ['id' => 1, 'name' => "Technology", 'parent' => null],
        ];
        $this->assertSame($exp, Arsse::$db->folderList("john.doe@example.com", null, false)->getAll());
        $exp = [
            ['id' => 4, 'name' => "Politics",   'parent' => null],
        ];
        $this->assertSame($exp, Arsse::$db->folderList("jane.doe@example.com", null, false)->getAll());
        $exp = [];
        $this->assertSame($exp, Arsse::$db->folderList("admin@example.net", null, false)->getAll());
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderList");
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.com", "folderList");
        Phake::verify(Arsse::$user)->authorize("admin@example.net", "folderList");
    }

    public function testListFoldersRecursively() {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null],
            ['id' => 6, 'name' => "Politics",   'parent' => 2],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1],
            ['id' => 2, 'name' => "Software",   'parent' => 1],
            ['id' => 1, 'name' => "Technology", 'parent' => null],
        ];
        $this->assertSame($exp, Arsse::$db->folderList("john.doe@example.com", null, true)->getAll());
        $exp = [
            ['id' => 6, 'name' => "Politics",   'parent' => 2],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1],
            ['id' => 2, 'name' => "Software",   'parent' => 1],
        ];
        $this->assertSame($exp, Arsse::$db->folderList("john.doe@example.com", 1, true)->getAll());
        $exp = [];
        $this->assertSame($exp, Arsse::$db->folderList("jane.doe@example.com", 4, true)->getAll());
        Phake::verify(Arsse::$user, Phake::times(2))->authorize("john.doe@example.com", "folderList");
        Phake::verify(Arsse::$user)->authorize("jane.doe@example.com", "folderList");
    }

    public function testListFoldersOfAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 2112);
    }

    public function testListFoldersOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    public function testListFoldersWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderList("john.doe@example.com");
    }

    public function testRemoveAFolder() {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 6));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        array_pop($state['arsse_folders']['rows']);
        $this->compareExpectations($state);
    }

    public function testRemoveAFolderTree() {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 1));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        foreach ([0,1,2,5] as $index) {
            unset($state['arsse_folders']['rows'][$index]);
        }
        $this->compareExpectations($state);
    }

    public function testRemoveAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 2112);
    }

    public function testRemoveAFolderOfTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    public function testRemoveAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderRemove("john.doe@example.com", 1);
    }

    public function testGetThePropertiesOfAFolder() {
        $exp = [
            'id'     => 6,
            'name'   => "Politics",
            'parent' => 2,
        ];
        $this->assertArraySubset($exp, Arsse::$db->folderPropertiesGet("john.doe@example.com", 6));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesGet");
    }

    public function testGetThePropertiesOfAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 2112);
    }

    public function testGetThePropertiesOfAFolderOfTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    public function testGetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 1);
    }

    public function testMakeNoChangesToAFolder() {
        $this->assertFalse(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, []));
    }

    public function testRenameAFolder() {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "Opinion"]));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][3] = "Opinion";
        $this->compareExpectations($state);
    }

    public function testRenameAFolderToTheEmptyString() {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => ""]));
    }

    public function testRenameAFolderToWhitespaceOnly() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "   "]));
    }

    public function testRenameAFolderToAnInvalidValue() {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => []]));
    }

    public function testMoveAFolder() {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => 5]));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][2] = 5; // parent should have changed
        $this->compareExpectations($state);
    }

    public function testMoveTheRootFolder() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 0, ['parent' => 1]);
    }

    public function testMoveAFolderToItsDescendant() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 3]);
    }

    public function testMoveAFolderToItself() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 1]);
    }

    public function testMoveAFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 2112]);
    }

    public function testMoveAFolderToAnInvalidParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => "ThisFolderDoesNotExist"]);
    }

    public function testCauseAFolderCollision() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => null]);
    }

    public function testCauseACompoundFolderCollision() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 3, ['parent' => null, 'name' => "Technology"]);
    }

    public function testSetThePropertiesOfAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 2112, ['parent' => null]);
    }

    public function testSetThePropertiesOfAFolderForTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 4, ['parent' => null]); // folder ID 4 belongs to Jane
    }

    public function testSetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => null]);
    }
}
