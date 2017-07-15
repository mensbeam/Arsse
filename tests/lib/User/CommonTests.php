<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\User;
use JKingWeb\Arsse\Data;
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
        Data::$conf = $conf;
        Data::$db = new Database();
        Data::$user = Phake::PartialMock(User::class);
        Phake::when(Data::$user)->authorize->thenReturn(true);
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
        $this->assertCount(0,Data::$user->list());
    }

    function testCheckIfAUserDoesNotExist() {
        $this->assertFalse(Data::$user->exists(self::USER1));
    }

    function testAddAUser() {
        Data::$user->add(self::USER1, "");
        $this->assertCount(1,Data::$user->list());
    }

    function testCheckIfAUserDoesExist() {
        Data::$user->add(self::USER1, "");
        $this->assertTrue(Data::$user->exists(self::USER1));
    }

    function testAddADuplicateUser() {
        Data::$user->add(self::USER1, "");
        $this->assertException("alreadyExists", "User");
        Data::$user->add(self::USER1, "");
    }

    function testAddMultipleUsers() {
        Data::$user->add(self::USER1, "");
        Data::$user->add(self::USER2, "");
        $this->assertCount(2,Data::$user->list());
    }

    function testRemoveAUser() {
        Data::$user->add(self::USER1, "");
        $this->assertCount(1,Data::$user->list());
        Data::$user->remove(self::USER1);
        $this->assertCount(0,Data::$user->list());
    }

    function testRemoveAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$user->remove(self::USER1);
    }

    function testAuthenticateAUser() {
        $_SERVER['PHP_AUTH_USER'] = self::USER1;
        $_SERVER['PHP_AUTH_PW'] = "secret";
        Data::$user->add(self::USER1, "secret");
        Data::$user->add(self::USER2, "");
        $this->assertTrue(Data::$user->auth());
        $this->assertTrue(Data::$user->auth(self::USER1, "secret"));
        $this->assertFalse(Data::$user->auth(self::USER1, "superman"));
        $this->assertTrue(Data::$user->auth(self::USER2, ""));
    }

    function testChangeAPassword() {
        Data::$user->add(self::USER1, "secret");
        $this->assertEquals("superman", Data::$user->passwordSet(self::USER1, "superman"));
        $this->assertTrue(Data::$user->auth(self::USER1, "superman"));
        $this->assertFalse(Data::$user->auth(self::USER1, "secret"));
        $this->assertEquals("", Data::$user->passwordSet(self::USER1, ""));
        $this->assertTrue(Data::$user->auth(self::USER1, ""));
        $this->assertEquals(Data::$conf->userTempPasswordLength, strlen(Data::$user->passwordSet(self::USER1)));
    }

    function testChangeAPasswordForAMissingUser() {
        $this->assertException("doesNotExist", "User");
        Data::$user->passwordSet(self::USER1, "superman");
    }

    function testGetThePropertiesOfAUser() {
        Data::$user->add(self::USER1, "secret");
        $p = Data::$user->propertiesGet(self::USER1);
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
        Data::$user->add(self::USER1, "secret");
        Data::$user->propertiesSet(self::USER1, $pSet);
        $p = Data::$user->propertiesGet(self::USER1);
        $this->assertArraySubset($pGet, $p);
        $this->assertArrayNotHasKey('password', $p);
        $this->assertFalse(Data::$user->auth(self::USER1, "superman"));
    }

    function testGetTheRightsOfAUser() {
        Data::$user->add(self::USER1, "");
        $this->assertEquals(Driver::RIGHTS_NONE, Data::$user->rightsGet(self::USER1));
    }

    function testSetTheRightsOfAUser() {
        Data::$user->add(self::USER1, "");
        Data::$user->rightsSet(self::USER1, Driver::RIGHTS_GLOBAL_ADMIN);
        $this->assertEquals(Driver::RIGHTS_GLOBAL_ADMIN, Data::$user->rightsGet(self::USER1));
    }
}