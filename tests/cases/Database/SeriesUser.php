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
                    'lang'     => 'str',
                    'tz'       => 'str',
                    'sort_asc' => 'bool',
                ],
                'rows' => [
                    ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW',1, 1, "en", "America/Toronto", 0], // password is hash of "secret"
                    ["jane.doe@example.com", "",2, 0, "fr", "Asia/Kuala_Lumpur", 1],
                    ["john.doe@example.com", "",3, 0, null, "Etc/UTC", 0],
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
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordGet("john.doe@example.org");
    }

    public function testAddANewUser(): void {
        $this->assertTrue(Arsse::$db->userAdd("john.doe@example.org", ""));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        $state['arsse_users']['rows'][] = ["john.doe@example.org"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddAnExistingUser(): void {
        $this->assertException("alreadyExists", "User");
        Arsse::$db->userAdd("john.doe@example.com", "");
    }

    public function testRemoveAUser(): void {
        $this->assertTrue(Arsse::$db->userRemove("admin@example.net"));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id']]);
        array_shift($state['arsse_users']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
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
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPasswordSet("john.doe@example.org", "secret");
    }
    
    /** @dataProvider provideMetaData */
    public function testGetMetadata(string $user, array $exp): void {
        $this->assertSame($exp, Arsse::$db->userPropertiesGet($user));
    }
    
    public function provideMetadata() {
        return [
            ["admin@example.net",    ['num' => 1, 'admin' => true,  'lang' => "en", 'tz' => "America/Toronto",   'sort_asc' => false]],
            ["jane.doe@example.com", ['num' => 2, 'admin' => false, 'lang' => "fr", 'tz' => "Asia/Kuala_Lumpur", 'sort_asc' => true]],
            ["john.doe@example.com", ['num' => 3, 'admin' => false, 'lang' => null, 'tz' => "Etc/UTC",           'sort_asc' => false]],
        ];
    }

    public function testGetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPropertiesGet("john.doe@example.org");
    }
    
    public function testSetMetadata(): void {
        $in = [
            'admin' => true, 
            'lang' => "en-ca", 
            'tz' => "Atlantic/Reykjavik", 
            'sort_asc' => true,
        ];
        $this->assertTrue(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin', 'lang', 'tz', 'sort_asc']]);
        $state['arsse_users']['rows'][2] = ["john.doe@example.com", 3, 1, "en-ca", "Atlantic/Reykjavik", 1];
        $this->compareExpectations(static::$drv, $state);
    }
    
    public function testSetNoMetadata(): void {
        $in = [
            'num' => 2112, 
            'blah' => "bloo"
        ];
        $this->assertFalse(Arsse::$db->userPropertiesSet("john.doe@example.com", $in));
        $state = $this->primeExpectations($this->data, ['arsse_users' => ['id', 'num', 'admin', 'lang', 'tz', 'sort_asc']]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testSetTheMetadataOfAMissingUser(): void {
        $this->assertException("doesNotExist", "User");
        Arsse::$db->userPropertiesSet("john.doe@example.org", ['admin' => true]);
    }
}
