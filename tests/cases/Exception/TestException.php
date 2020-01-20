<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Exception;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Lang;
use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Lang\Exception as LangException;

/** @covers \JKingWeb\Arsse\AbstractException */
class TestException extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData(false);
        // create a mock Lang object so as not to create a dependency loop
        Arsse::$lang = \Phake::mock(Lang::class);
        \Phake::when(Arsse::$lang)->msg->thenReturn("");
    }

    public function tearDown(): void {
        // verify calls to the mock Lang object
        \Phake::verify(Arsse::$lang, \Phake::atLeast(0))->msg($this->isType("string"), $this->anything());
        \Phake::verifyNoOtherInteractions(Arsse::$lang);
        // clean up
        self::clearData(true);
    }

    public function testBaseClass():void {
        $this->assertException("unknown");
        throw new Exception("unknown");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithoutMessage():void {
        $this->assertException("unknown");
        throw new Exception();
    }

    /**
     * @depends testBaseClass
     */
    public function testDerivedClass():void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing");
    }

    /**
     * @depends testDerivedClass
     */
    public function testDerivedClassWithMessageParameters():void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing", "en");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithUnknownCode():void {
        $this->assertException("uncoded");
        throw new Exception("testThisExceptionMessageDoesNotExist");
    }

    /**
     * @depends testBaseClassWithUnknownCode
     */
    public function testDerivedClassWithMissingMessage():void {
        $this->assertException("uncoded");
        throw new LangException("testThisExceptionMessageDoesNotExist");
    }

    /** @covers \JKingWeb\Arsse\ExceptionFatal */
    public function testFatalException():void {
        $this->expectException('JKingWeb\Arsse\ExceptionFatal');
        throw new \JKingWeb\Arsse\ExceptionFatal("");
    }
}
