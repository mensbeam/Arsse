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
        $this->langMock = $this->mock(Lang::class);
        $this->langMock->msg->returns("");
        Arsse::$lang = $this->langMock->get();
    }

    public function testBaseClass(): void {
        $this->assertException("unknown");
        throw new Exception("unknown");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithoutMessage(): void {
        $this->assertException("unknown");
        throw new Exception();
    }

    /**
     * @depends testBaseClass
     */
    public function testDerivedClass(): void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing");
    }

    /**
     * @depends testDerivedClass
     */
    public function testDerivedClassWithMessageParameters(): void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing", "en");
    }

    /**
     * @depends testBaseClass
     */
    public function testBaseClassWithUnknownCode(): void {
        $this->assertException("uncoded");
        throw new Exception("testThisExceptionMessageDoesNotExist");
    }

    /**
     * @depends testBaseClassWithUnknownCode
     */
    public function testDerivedClassWithMissingMessage(): void {
        $this->assertException("uncoded");
        throw new LangException("testThisExceptionMessageDoesNotExist");
    }

    /** @covers \JKingWeb\Arsse\ExceptionFatal */
    public function testFatalException(): void {
        $this->expectException('JKingWeb\Arsse\ExceptionFatal');
        throw new \JKingWeb\Arsse\ExceptionFatal("");
    }

    public function testGetExceptionSymbol(): void {
        $e = new LangException("stringMissing", ['msgID' => "OOK"]);
        $this->assertSame("stringMissing", $e->getSymbol());
    }

    public function testGetExceptionParams(): void {
        $e = new LangException("stringMissing", ['msgID' => "OOK"]);
        $this->assertSame(['msgID' => "OOK"], $e->getParams());
    }
}
