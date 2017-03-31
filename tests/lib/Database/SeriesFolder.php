<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\User\Driver as UserDriver;
use Phake;

trait SeriesFolder {
    function setUpSeries() {
        $data = [
            'arsse_folders' => [
                'columns' => [
                    'id'     => "int",
                    'owner'  => "str",
                    'parent' => "int",
                    'root'   => "int",
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
                    [1, "john.doe@example.com", null, null, "Technology"],
                    [2, "john.doe@example.com",    1,    1, "Software"],
                    [3, "john.doe@example.com",    1,    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, null, "Politics"],        
                    [5, "john.doe@example.com", null, null, "Politics"],
                    [6, "john.doe@example.com",    2,    1, "Politics"],
                ]
            ]
        ];
        // merge folder table with base user table
        $this->data = array_merge($this->data, $data);
        $this->primeDatabase($this->data);
    }

    function testAddARootFolder() {
        $user = "john.doe@example.com";
        $this->assertSame(7, Data::$db->folderAdd($user, ['name' => "Entertainment"]));
        Phake::verify(Data::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'root', 'name']]);
        $state['arsse_folders']['rows'][] = [7, $user, null, null, "Entertainment"];
        $this->compareExpectations($state);
    }

    function testAddADuplicateRootFolder() {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Data::$db->folderAdd("john.doe@example.com", ['name' => "Politics"]);
    }

    function testAddANestedFolder() {
        $user = "john.doe@example.com";
        $this->assertSame(7, Data::$db->folderAdd($user, ['name' => "GNOME", 'parent' => 2]));
        Phake::verify(Data::$user)->authorize($user, "folderAdd");
        $state = $this->primeExpectations($this->data, ['arsse_folders' => ['id','owner', 'parent', 'root', 'name']]);
        $state['arsse_folders']['rows'][] = [7, $user, 2, 1, "GNOME"];
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

    function testAddAFolderForAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$db->folderAdd("john.doe@example.org", ['name' => "Sociology"]);
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
}