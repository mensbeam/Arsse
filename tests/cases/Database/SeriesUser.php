<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;

trait SeriesUser {
    protected static $drv;

    protected function setUpSeriesUser(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num", "admin"],
                'rows'    => [
                    ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', 1, 1], // password is hash of "secret"
                    ["jane.doe@example.com", "", 2, 0],
                    ["john.doe@example.com", "", 3, 0],
                ],
            ],
            'arsse_user_meta' => [
                'columns' => ["owner", "key", "value"],
                'rows'    => [
                    ["admin@example.net", "lang", "en"],
                    ["admin@example.net", "tz", "America/Toronto"],
                    ["jane.doe@example.com", "lang", "fr"],
                    ["jane.doe@example.com", "tz", "Asia/Kuala_Lumpur"],
                    ["john.doe@example.com", "lang", "de"],
                ],
            ],
        ];
    }

    protected function tearDownSeriesUser(): void {
        unset($this->data);
    }

    //#[CoversMethod(Database::class, "userExists")]
    public function testCheckThatAUserExists(): void {
        $this->assertTrue(Arsse::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Arsse::$db->userExists("jane.doe@example.org"));
        $this->compareExpectations(static::$drv, $this->data);
    }

    //#[CoversMethod(Database::class, "userPasswordGet")]
    public function testGetAPassword(): void {
        $hash = Arsse::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        $this->assertTrue(password_verify("secret", $hash));
    }

    //#[CoversMethod(Database::class, "userPasswordGet")]
    public function testGetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPasswordGet("john.doe@example.org");
    }

    //#[CoversMethod(Database::class, "userAdd")]
    public function testAddANewUser(): void {
        $this->assertTrue(Arsse::$db->userAdd("john.doe@example.org", ""));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org"];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "userAdd")]
    public function testAddAnExistingUser(): void {
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    //#[CoversMethod(Database::class, "userRemove")]
    public function testRemoveAUser(): void {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "userRemove")]
    public function testRemoveAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userRemove("john.doe@example.org");
    }

    //#[CoversMethod(Database::class, "userList")]
    public function testListAllUsers(): void {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList());
    }

    //#[CoversMethod(Database::class, "userPasswordSet")]
    public function testSetAPassword(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, $pass));
        $hash = Arsse::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }

    //#[CoversMethod(Database::class, "userPasswordSet")]
    public function testUnsetAPassword(): void {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, null));
        $this->assertNull(Arsse::$db->userPasswordGet($user));
    }

    //#[CoversMethod(Database::class, "userPasswordSet")]
    public function testSetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }

    #[DataProvider("provideMetaData")]
    //#[CoversMethod(Database::class, "userPropertiesGet")]
    public function testGetMetadata(string $user, array $exp): void {
        $this->assertSame($exp, Arsse::$db->userPropertiesGet($user));
    }

    public static function provideMetadata(): iterable {
        return [
            ["admin@example.net",    ['num' => 1, 'admin' => 1, 'lang' => "en", 'tz' => "America/Toronto"]],
            ["jane.doe@example.com", ['num' => 2, 'admin' => 0, 'lang' => "fr", 'tz' => "Asia/Kuala_Lumpur"]],
            ["john.doe@example.com", ['num' => 3, 'admin' => 0, 'lang' => "de"]],
        ];
    }

    //#[CoversMethod(Database::class, "userPropertiesGet")]
    public function testGetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPropertiesGet("john.doe@example.org");
    }

    //#[CoversMethod(Database::class, "userPropertiesSet")]
    public function testSetMetadata(): void {
        $in = [
            'admin'    => true,
            'root_folder_name' => "Uncategorized",
            'tz'               => "Atlantic/Reykjavik",
        ];
        $this->assertTrue(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin'], 'arsse_user_meta' => ["owner", "key", "value"]]);
        $state['arsse_users']['rows'][2][2] = 1;
        $state['arsse_user_meta']['rows'][] = ["john.doe@example.com", "root_folder_name", "Uncategorized"];
        $state['arsse_user_meta']['rows'][] = ["john.doe@example.com", "tz", "Atlantic/Reykjavik"];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "userPropertiesSet")]
    public function testSetNoMetadata(): void {
        $in = [
            'num'        => 2112,
        ];
        $this->assertTrue(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin'], 'arsse_user_meta' => ["owner", "key", "value"]]);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "userPropertiesSet")]
    public function testSetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPropertiesSet("john.doe@example.org", ['admin' => true]);
    }

    //#[CoversMethod(Database::class, "userLookup")]
    public function testLookUpAUserByNumber(): void {
        $this->assertSame("admin@example.net", Arsse::$db->userLookup(1));
        $this->assertSame("jane.doe@example.com", Arsse::$db->userLookup(2));
        $this->assertSame("john.doe@example.com", Arsse::$db->userLookup(3));
    }

    //#[CoversMethod(Database::class, "userLookup")]
    public function testLookUpAMissingUserByNumber(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userLookup(2112);
    }

    //#[CoversMethod(Database::class, "userRename")]
    public function testRenameAUser(): void {
        $this->assertTrue(Arsse::$db->userRename("john.doe@example.com", "juan.doe@example.com"));
        $state = $this->primeExpectations($this->data, [
            'arsse_users'     => ['id', 'num'],
            'arsse_user_meta' => ["owner", "key", "value"],
        ]);
        $state['arsse_users']['rows'][2][0] = "juan.doe@example.com";
        $state['arsse_user_meta']['rows'][4][0] = "juan.doe@example.com";
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "userRename")]
    public function testRenameAUserToTheSameName(): void {
        $this->assertFalse(Arsse::$db->userRename("john.doe@example.com", "john.doe@example.com"));
    }

    //#[CoversMethod(Database::class, "userRename")]
    public function testRenameAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userRename("juan.doe@example.com", "john.doe@example.com");
    }

    //#[CoversMethod(Database::class, "userRename")]
    public function testRenameAUserToADuplicateName(): void {
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        Arsse::$db->userRename("john.doe@example.com", "jane.doe@example.com");
    }

    //#[CoversMethod(Database::class, "userAdd")]
    public function testAddFirstUser(): void {
        // first truncate the users table
        static::$drv->exec("DELETE FROM arsse_users");
        // add a user; if the max of the num column is not properly coalesced, this will result in a constraint violation
        $this->assertTrue(Arsse::$db->userAdd("john.doe@example.com", ""));
    }
}
