<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Driver as UserDriver;
use Phake;

trait SeriesFolder {
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

    function testAddARootFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "Entertainment"]));
        Phake::verify(Arsse::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, null, "Entertainment"];
        $this->compareExpectations($state);
    }

    function testAddADuplicateRootFolder() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Politics"]);
    }

    function testAddANestedFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Arsse::$db->folderAdd($user, ['name' => "GNOME", 'parent' => 2]));
        Phake::verify(Arsse::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, 2, "GNOME"];
        $this->compareExpectations($state);
    }

    function testAddANestedFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 2112]);
    }

    function testAddANestedFolderForTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 4]); // folder ID 4 belongs to Jane
    }

    function testAddAFolderWithAMissingName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", []);
    }

    function testAddAFolderWithABlankName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => ""]);
    }

    function testAddAFolderWithAWhitespaceName() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => " "]);
    }

    function testAddAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderAdd("john.doe@example.com", ['name' => "Sociology"]);
    }

    function testListRootFolders() {
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

    function testListFoldersRecursively() {
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

    function testListFoldersOfAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 2112);
    }

    function testListFoldersOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderList("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testListFoldersWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderList("john.doe@example.com");
    }

    function testRemoveAFolder() {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 6));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        array_pop($state['arsse_folders']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAFolderTree() {
        $this->assertTrue(Arsse::$db->folderRemove("john.doe@example.com", 1));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        foreach([0,1,2,5] as $index) {
            unset($state['arsse_folders']['rows'][$index]);
        }
        $this->compareExpectations($state);
    }

    function testRemoveAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 2112);
    }

    function testRemoveAFolderOfTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderRemove("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testRemoveAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderRemove("john.doe@example.com", 1);
    }

    function testGetThePropertiesOfAFolder() {
        $exp = [
            'id'     => 6,
            'name'   => "Politics",
            'parent' => 2,
        ];
        $this->assertArraySubset($exp, Arsse::$db->folderPropertiesGet("john.doe@example.com", 6));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesGet");
    }

    function testGetThePropertiesOfAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 2112);
    }

    function testGetThePropertiesOfAFolderOfTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testGetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderPropertiesGet("john.doe@example.com", 1);
    }

    function testRenameAFolder() {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "Opinion"]));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][3] = "Opinion";
        $this->compareExpectations($state);
    }

    function testRenameAFolderToTheEmptyString() {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => ""]));
    }

    function testRenameAFolderToWhitespaceOnly() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "   "]));
    }

    function testMoveAFolder() {
        $this->assertTrue(Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => 5]));
        Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][2] = 5; // parent should have changed
        $this->compareExpectations($state);
    }

    function testMoveAFolderToItsDescendant() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 3]);
    }

    function testMoveAFolderToItself() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 1]);
    }

    function testMoveAFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 2112]);
    }

    function testCauseAFolderCollision() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => null]);
    }

    function testSetThePropertiesOfAMissingFolder() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 2112, ['parent' => null]);
    }

    function testSetThePropertiesOfAFolderForTheWrongOwner() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 4, ['parent' => null]); // folder ID 4 belongs to Jane
    }

    function testSetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => null]);
    }
}