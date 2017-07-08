<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
Use Phake;


class TestException extends Test\AbstractTest {
    function setUp() {
        $this->clearData(false);
        // create a mock Lang object so as not to create a dependency loop
        Data::$l = Phake::mock(Lang::class);
        Phake::when(Data::$l)->msg->thenReturn("");
    }

    function tearDown() {
        // verify calls to the mock Lang object
        Phake::verify(Data::$l, Phake::atLeast(0))->msg($this->isType("string"), $this->anything());
        Phake::verifyNoOtherInteractions(Data::$l);
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
