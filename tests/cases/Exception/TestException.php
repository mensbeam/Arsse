<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use Phake;

/** @covers \JKingWeb\Arsse\AbstractException */
class TestException extends Test\AbstractTest {
    public function setUp() {
        $this->clearData(false);
        // create a mock Lang object so as not to create a dependency loop
        Arsse::$lang = Phake::mock(Lang::class);
        Phake::when(Arsse::$lang)->msg->thenReturn("");
    }

    public function tearDown() {
        // verify calls to the mock Lang object
        Phake::verify(Arsse::$lang, Phake::atLeast(0))->msg($this->isType("string"), $this->anything());
        Phake::verifyNoOtherInteractions(Arsse::$lang);
        // clean up
        $this->clearData(true);
    }

    public function testBaseClass() {
        $this->assertException("unknown");
        throw new Exception("unknown");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithoutMessage() {
        $this->assertException("unknown");
        throw new Exception();
    }

    /**
     * @depends testBaseClass
     */
    public function testDerivedClass() {
        $this->assertException("fileMissing", "Lang");
        throw new Lang\Exception("fileMissing");
    }

    /**
     * @depends testDerivedClass
     */
    public function testDerivedClassWithMessageParameters() {
        $this->assertException("fileMissing", "Lang");
        throw new Lang\Exception("fileMissing", "en");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithUnknownCode() {
        $this->assertException("uncoded");
        throw new Exception("testThisExceptionMessageDoesNotExist");
    }

    /**
     * @depends testBaseClassWithUnknownCode
     */
    public function testDerivedClassWithMissingMessage() {
        $this->assertException("uncoded");
        throw new Lang\Exception("testThisExceptionMessageDoesNotExist");
    }
}