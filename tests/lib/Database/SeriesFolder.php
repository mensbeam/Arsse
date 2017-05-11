<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\User\Driver as UserDriver;
use Phake;

trait SeriesFolder {
    function setUpSeries() {
        $this->primeDatabase($this->data);
    }

    function testAddARootFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Data::$db->folderAdd($user, ['name' => "Entertainment"]));
        Phake::verify(Data::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, null, "Entertainment"];
        $this->compareExpectations($state);
    }

    function testAddADuplicateRootFolder() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => "Politics"]);
    }

    function testAddANestedFolder() {
        $user = "john.doe@example.com";
        $folderID = $this->nextID("arsse_folders");
        $this->assertSame($folderID, Data::$db->folderAdd($user, ['name' => "GNOME", 'parent' => 2]));
        Phake::verify(Data::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][] = [$folderID, $user, 2, "GNOME"];
        $this->compareExpectations($state);
    }

    function testAddANestedFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 2112]);
    }

    function testAddANestedFolderForTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => "Sociology", 'parent' => 4]); // folder ID 4 belongs to Jane
    }

    function testAddAFolderWithAMissingName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", []);
    }

    function testAddAFolderWithABlankName() {
        $this->assertException("missing", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => ""]);
    }

    function testAddAFolderWithAWhitespaceName() {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => " "]);
    }

    function testAddAFolderWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->folderAdd("john.doe@example.com", ['name' => "Sociology"]);
    }

    function testListRootFolders() {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null],
            ['id' => 1, 'name' => "Technology", 'parent' => null],
        ];
        $this->assertSame($exp, Data::$db->folderList("john.doe@example.com", null, false)->getAll());
        $exp = [
            ['id' => 4, 'name' => "Politics",   'parent' => null],
        ];
        $this->assertSame($exp, Data::$db->folderList("jane.doe@example.com", null, false)->getAll());
        $exp = [];
        $this->assertSame($exp, Data::$db->folderList("admin@example.net", null, false)->getAll());
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderList");
        Phake::verify(Data::$user)->authorize("jane.doe@example.com", "folderList");
        Phake::verify(Data::$user)->authorize("admin@example.net", "folderList");
    }

    function testListFoldersRecursively() {
        $exp = [
            ['id' => 5, 'name' => "Politics",   'parent' => null],
            ['id' => 6, 'name' => "Politics",   'parent' => 2],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1],
            ['id' => 2, 'name' => "Software",   'parent' => 1],
            ['id' => 1, 'name' => "Technology", 'parent' => null],
        ];
        $this->assertSame($exp, Data::$db->folderList("john.doe@example.com", null, true)->getAll());
        $exp = [
            ['id' => 6, 'name' => "Politics",   'parent' => 2],
            ['id' => 3, 'name' => "Rocketry",   'parent' => 1],
            ['id' => 2, 'name' => "Software",   'parent' => 1],
        ];
        $this->assertSame($exp, Data::$db->folderList("john.doe@example.com", 1, true)->getAll());
        $exp = [];
        $this->assertSame($exp, Data::$db->folderList("jane.doe@example.com", 4, true)->getAll());
        Phake::verify(Data::$user, Phake::times(2))->authorize("john.doe@example.com", "folderList");
        Phake::verify(Data::$user)->authorize("jane.doe@example.com", "folderList");
    }

    function testListFoldersOfAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderList("john.doe@example.com", 2112);
    }

    function testListFoldersOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderList("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testListFoldersWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->folderList("john.doe@example.com");
    }

    function testRemoveAFolder() {
        $this->assertTrue(Data::$db->folderRemove("john.doe@example.com", 6));
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        array_pop($state['arsse_folders']['rows']);
        $this->compareExpectations($state);
    }

    function testRemoveAFolderTree() {
        $this->assertTrue(Data::$db->folderRemove("john.doe@example.com", 1));
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderRemove");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        foreach([0,1,2,5] as $index) {
            unset($state['arsse_folders']['rows'][$index]);
        }
        $this->compareExpectations($state);
    }

    function testRemoveAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderRemove("john.doe@example.com", 2112);
    }

    function testRemoveAFolderOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderRemove("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testRemoveAFolderWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->folderRemove("john.doe@example.com", 1);
    }

    function testGetThePropertiesOfAFolder() {
        $exp = [
            'id'     => 6,
            'name'   => "Politics",
            'parent' => 2,
        ];
        $this->assertArraySubset($exp, Data::$db->folderPropertiesGet("john.doe@example.com", 6));
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderPropertiesGet");
    }

    function testGetThePropertiesOfAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderPropertiesGet("john.doe@example.com", 2112);
    }

    function testGetThePropertiesOfAFolderOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderPropertiesGet("john.doe@example.com", 4); // folder ID 4 belongs to Jane
    }

    function testGetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->folderPropertiesGet("john.doe@example.com", 1);
    }

    function testRenameAFolder() {
        $this->assertTrue(Data::$db->folderPropertiesSet("john.doe@example.com", 6, ['name' => "Opinion"]));
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][3] = "Opinion";
        $this->compareExpectations($state);
    }

    function testMoveAFolder() {
        $this->assertTrue(Data::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => 5]));
        Phake::verify(Data::$user)->authorize("john.doe@example.com", "folderPropertiesSet");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'name']]);
        $state['arsse_folders']['rows'][5][2] = 5; // parent should have changed
        $this->compareExpectations($state);
    }

    function testMoveAFolderToItsDescendant() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 3]);
    }

    function testMoveAFolderToItself() {
        $this->assertException("circularDependence", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 1]);
    }

    function testMoveAFolderToAMissingParent() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => 2112]);
    }

    function testCauseAFolderCollision() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 6, ['parent' => null]);
    }

    function testSetThePropertiesOfAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 2112, ['parent' => null]);
    }

    function testSetThePropertiesOfAFolderOfTheWrongOwner() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->folderPropertiesSet("john.doe@example.com", 4, ['parent' => null]); // folder ID 4 belongs to Jane
    }

    function testSetThePropertiesOfAFolderWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->folderPropertiesSet("john.doe@example.com", 1, ['parent' => null]);
    }
}