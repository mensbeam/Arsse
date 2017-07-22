<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\User;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\User\Driver;
use Phake;

trait CommonTests {

    function setUp() {
        $this->clearData();
        $conf = new Conf();
        $conf->userDriver = $this->drv;
        $conf->userPreAuth = false;
        Arsse::$conf = $conf;
        Arsse::$db = new Database();
        Arsse::$user = Phake::partialMock(User::class);
        Phake::when(Arsse::$user)->authorize->thenReturn(true);
        $_SERVER['PHP_AUTH_USER'] = self::USER1;
        $_SERVER['PHP_AUTH_PW'] = "secret";
        // call the additional setup method if it exists
        if(method_exists($this, "setUpSeries")) $this->setUpSeries();
    }

    function tearDown() {
        $this->clearData();
        // call the additional teardiwn method if it exists
        if(method_exists($this, "tearDownSeries")) $this->tearDownSeries();
    }

    function testListUsers() {
        $this->assertCount(0,Arsse::$user->list());
    }

    function testCheckIfAUserDoesNotExist() {
        $this->assertFalse(Arsse::$user->exists(self::USER1));
    }

    function testAddAUser() {
        Arsse::$user->add(self::USER1, "");
        $this->assertCount(1,Arsse::$user->list());
    }

    function testCheckIfAUserDoesExist() {
        Arsse::$user->add(self::USER1, "");
        $this->assertTrue(Arsse::$user->exists(self::USER1));
    }

    function testAddADuplicateUser() {
        Arsse::$user->add(self::USER1, "");
        $this->assertException("alreadyExists", "User");
        Arsse::$user->add(self::USER1, "");
    }

    function testAddMultipleUsers() {
        Arsse::$user->add(self::USER1, "");
        Arsse::$user->add(self::USER2, "");
        $this->assertCount(2,Arsse::$user->list());
    }

    function testRemoveAUser() {
        Arsse::$user->add(self::USER1, "");
        $this->assertCount(1,Arsse::$user->list());
        Arsse::$user->remove(self::USER1);
        $this->assertCount(0,Arsse::$user->list());
    }

    function testRemoveAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$user->remove(self::USER1);
    }

    function testAuthenticateAUser() {
        $_SERVER['PHP_AUTH_USER'] = self::USER1;
        $_SERVER['PHP_AUTH_PW'] = "secret";
        Arsse::$user->add(self::USER1, "secret");
        Arsse::$user->add(self::USER2, "");
        $this->assertTrue(Arsse::$user->auth());
        $this->assertTrue(Arsse::$user->auth(self::USER1, "secret"));
        $this->assertFalse(Arsse::$user->auth(self::USER1, "superman"));
        $this->assertTrue(Arsse::$user->auth(self::USER2, ""));
    }

    function testChangeAPassword() {
        Arsse::$user->add(self::USER1, "secret");
        $this->assertEquals("superman", Arsse::$user->passwordSet(self::USER1, "superman"));
        $this->assertTrue(Arsse::$user->auth(self::USER1, "superman"));
        $this->assertFalse(Arsse::$user->auth(self::USER1, "secret"));
        $this->assertEquals("", Arsse::$user->passwordSet(self::USER1, ""));
        $this->assertTrue(Arsse::$user->auth(self::USER1, ""));
        $this->assertEquals(Arsse::$conf->userTempPasswordLength, strlen(Arsse::$user->passwordSet(self::USER1)));
    }

    function testChangeAPasswordForAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Arsse::$user->passwordSet(self::USER1, "superman");
    }

    function testGetThePropertiesOfAUser() {
        Arsse::$user->add(self::USER1, "secret");
        $p = Arsse::$user->propertiesGet(self::USER1);
        $this->assertArrayHasKey('id', $p);
        $this->assertArrayHasKey('name', $p);
        $this->assertArrayHasKey('domain', $p);
        $this->assertArrayHasKey('rights', $p);
        $this->assertArrayNotHasKey('password', $p);
        $this->assertEquals(self::USER1, $p['name']);
    }

    function testSetThePropertiesOfAUser() {
        $pSet = [
            'name'     => 'John Doe',
            'id'       => 'invalid',
            'domain'   => 'localhost',
            'rights'   => Driver::RIGHTS_GLOBAL_ADMIN,
            'password' => 'superman',
        ];
        $pGet = [
            'name'   => 'John Doe',
            'id'     => self::USER1,
            'domain' => 'example.com',
            'rights' => Driver::RIGHTS_NONE,
        ];
        Arsse::$user->add(self::USER1, "secret");
        Arsse::$user->propertiesSet(self::USER1, $pSet);
        $p = Arsse::$user->propertiesGet(self::USER1);
        $this->assertArraySubset($pGet, $p);
        $this->assertArrayNotHasKey('password', $p);
        $this->assertFalse(Arsse::$user->auth(self::USER1, "superman"));
    }

    function testGetTheRightsOfAUser() {
        Arsse::$user->add(self::USER1, "");
        $this->assertEquals(Driver::RIGHTS_NONE, Arsse::$user->rightsGet(self::USER1));
    }

    function testSetTheRightsOfAUser() {
        Arsse::$user->add(self::USER1, "");
        Arsse::$user->rightsSet(self::USER1, Driver::RIGHTS_GLOBAL_ADMIN);
        $this->assertEquals(Driver::RIGHTS_GLOBAL_ADMIN, Arsse::$user->rightsGet(self::USER1));
    }
}