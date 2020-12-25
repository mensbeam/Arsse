<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesUser {
    protected function setUpSeriesUser(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                    'num'      => 'int',
                    'admin'    => 'bool',
                ],
                'rows' => [
                    ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', 1, 1], // password is hash of "secret"
                    ["jane.doe@example.com", "", 2, 0],
                    ["john.doe@example.com", "", 3, 0],
                ],
            ],
            'arsse_user_meta' => [
                'columns' => [
                    'owner' => "str",
                    'key'   => "str",
                    'value' => "str",
                ],
                'rows' => [
                    ["admin@example.net", "lang", "en"],
                    ["admin@example.net", "tz", "America/Toronto"],
                    ["admin@example.net", "sort_asc", "0"],
                    ["jane.doe@example.com", "lang", "fr"],
                    ["jane.doe@example.com", "tz", "Asia/Kuala_Lumpur"],
                    ["jane.doe@example.com", "sort_asc", "1"],
                    ["john.doe@example.com", "stylesheet", "body {background:lightgray}"],
                ],
            ],
        ];
    }

    protected function tearDownSeriesUser(): void {
        unset($this->data);
    }

    public function testCheckThatAUserExists(): void {
        $this->assertTrue(Arsse::$db->userExists("jane.doe@example.com"));
        $this->assertFalse(Arsse::$db->userExists("jane.doe@example.org"));
        $this->compareExpectations(static::$drv, $this->data);
    }

    public function testGetAPassword(): void {
        $hash = Arsse::$db->userPasswordGet("admin@example.net");
        $this->assertSame('$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', $hash);
        $this->assertTrue(password_verify("secret", $hash));
    }

    public function testGetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPasswordGet("john.doe@example.org");
    }

    public function testAddANewUser(): void {
        $this->assertTrue(Arsse::$db->userAdd("john.doe@example.org", ""));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddAnExistingUser(): void {
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    public function testRemoveAUser(): void {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userRemove("john.doe@example.org");
    }

    public function testListAllUsers(): void {
        $users = ["admin@example.net", "jane.doe@example.com", "john.doe@example.com"];
        $this->assertSame($users, Arsse::$db->userList());
    }

    /**
     * @depends testGetAPassword
     */
    public function testSetAPassword(): void {
        $user = "john.doe@example.com";
        $pass = "secret";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, $pass));
        $hash = Arsse::$db->userPasswordGet($user);
        $this->assertNotEquals("", $hash);
        $this->assertTrue(password_verify($pass, $hash), "Failed verifying password of $user '$pass' against hash '$hash'.");
    }

    public function testUnsetAPassword(): void {
        $user = "john.doe@example.com";
        $this->assertEquals("", Arsse::$db->userPasswordGet($user));
        $this->assertTrue(Arsse::$db->userPasswordSet($user, null));
        $this->assertNull(Arsse::$db->userPasswordGet($user));
    }

    public function testSetThePasswordOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }

    /** @dataProvider provideMetaData */
    public function testGetMetadata(string $user, bool $includeLarge, array $exp): void {
        $this->assertSame($exp, Arsse::$db->userPropertiesGet($user, $includeLarge));
    }

    public function provideMetadata(): iterable {
        return [
            ["admin@example.net",    true,  ['num' => 1, 'admin' => 1, 'lang' => "en", 'sort_asc' => "0", 'tz' => "America/Toronto"]],
            ["jane.doe@example.com", true,  ['num' => 2, 'admin' => 0, 'lang' => "fr", 'sort_asc' => "1", 'tz' => "Asia/Kuala_Lumpur"]],
            ["john.doe@example.com", true,  ['num' => 3, 'admin' => 0, 'stylesheet' => "body {background:lightgray}"]],
            ["admin@example.net",    false, ['num' => 1, 'admin' => 1, 'lang' => "en", 'sort_asc' => "0", 'tz' => "America/Toronto"]],
            ["jane.doe@example.com", false, ['num' => 2, 'admin' => 0, 'lang' => "fr", 'sort_asc' => "1", 'tz' => "Asia/Kuala_Lumpur"]],
            ["john.doe@example.com", false, ['num' => 3, 'admin' => 0]],
        ];
    }

    public function testGetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPropertiesGet("john.doe@example.org");
    }

    public function testSetMetadata(): void {
        $in = [
            'admin'    => true,
            'lang'     => "en-ca",
            'tz'       => "Atlantic/Reykjavik",
            'sort_asc' => true,
        ];
        $this->assertTrue(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin'], 'arsse_user_meta' => ["owner", "key", "value"]]);
        $state['arsse_users']['rows'][2][2] = 1;
        $state['arsse_user_meta']['rows'][] = ["john.doe@example.com", "lang", "en-ca"];
        $state['arsse_user_meta']['rows'][] = ["john.doe@example.com", "tz", "Atlantic/Reykjavik"];
        $state['arsse_user_meta']['rows'][] = ["john.doe@example.com", "sort_asc", "1"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testSetNoMetadata(): void {
        $in = [
            'num'        => 2112,
            'stylesheet' => "body {background:lightgray}",
        ];
        $this->assertTrue(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin'], 'arsse_user_meta' => ["owner", "key", "value"]]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testSetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userPropertiesSet("john.doe@example.org", ['admin' => true]);
    }

    public function testLookUpAUserByNumber(): void {
        $this->assertSame("admin@example.net", Arsse::$db->userLookup(1));
        $this->assertSame("jane.doe@example.com", Arsse::$db->userLookup(2));
        $this->assertSame("john.doe@example.com", Arsse::$db->userLookup(3));
    }

    public function testLookUpAMissingUserByNumber(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userLookup(2112);
    }

    public function testRenameAUser(): void {
        $this->assertTrue(Arsse::$db->userRename("john.doe@example.com", "juan.doe@example.com"));
        $state = $this->primeExpectations($this->data, [
            'arsse_users'     => ['id', 'num'], 
            'arsse_user_meta' => ["owner", "key", "value"]
        ]);
        $state['arsse_users']['rows'][2][0] = "juan.doe@example.com";
        $state['arsse_user_meta']['rows'][6][0] = "juan.doe@example.com";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameAUserToTheSameName(): void {
        $this->assertFalse(Arsse::$db->userRename("john.doe@example.com", "john.doe@example.com"));
    }

    public function testRenameAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        Arsse::$db->userRename("juan.doe@example.com", "john.doe@example.com");
    }

    public function testRenameAUserToADuplicateName(): void {
        $this->assertException("alreadyExists", "User", "ExceptionConflict");
        Arsse::$db->userRename("john.doe@example.com", "jane.doe@example.com");
    }
}
