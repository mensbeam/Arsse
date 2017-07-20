<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
Use Phake;


/** @covers \JKingWeb\Arsse\AbstractException */
class TestException extends Test\AbstractTest {
    function setUp() {
        $this->clearData(false);
        // create a mock Lang object so as not to create a dependency loop
        Arsse::$lang = Phake::mock(Lang::class);
        Phake::when(Arsse::$lang)->msg->thenReturn("");
    }

    function tearDown() {
        // verify calls to the mock Lang object
        Phake::verify(Arsse::$lang, Phake::atLeast(0))->msg($this->isType("string"), $this->anything());
        Phake::verifyNoOtherInteractions(Arsse::$lang);
        // clean up
        $this->clearData(true);
    }

    function testBaseClass() {
        $this->assertException("unknown");
        throw new Exception("unknown");
    }

    /**
     * @depends testBaseClass
     */
    function testBaseClassWithoutMessage() {
        $this->assertException("unknown");
        throw new Exception();
    }

    /**
     * @depends testBaseClass
     */
    function testDerivedClass() {
        $this->assertException("fileMissing", "Lang");
        throw new Lang\Exception("fileMissing");
    }

    /**
     * @depends testDerivedClass
     */
    function testDerivedClassWithMessageParameters() {
        $this->assertException("fileMissing", "Lang");
        throw new Lang\Exception("fileMissing", "en");
    }

    /**
     * @depends testBaseClass
     */
    function testBaseClassWithUnknownCode() {
        $this->assertException("uncoded");
        throw new Exception("testThisExceptionMessageDoesNotExist");
    }

    /**
     * @depends testBaseClassWithUnknownCode
     */
    function testDerivedClassWithMissingMessage() {
        $this->assertException("uncoded");
        throw new Lang\Exception("testThisExceptionMessageDoesNotExist");
    }
}
