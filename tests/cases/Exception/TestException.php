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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;

#[CoversClass(\JKingWeb\Arsse\AbstractException::class)]
#[CoversClass(\JKingWeb\Arsse\ExceptionFatal::class)]
class TestException extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData(false);
        // create a mock Lang object so as not to create a dependency loop
        Arsse::$lang = \Phake::mock(Lang::class);
        \Phake::when(Arsse::$lang)->msg->thenReturn("");
    }

    public function testBaseClass(): void {
        $this->assertException("unknown");
        throw new Exception("unknown");
    }

    #[Depends('testBaseClass')]
    public function testBaseClassWithoutMessage(): void {
        $this->assertException("unknown");
        throw new Exception;
    }

    #[Depends('testBaseClass')]
    public function testDerivedClass(): void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing");
    }

    #[Depends('testDerivedClass')]
    public function testDerivedClassWithMessageParameters(): void {
        $this->assertException("fileMissing", "Lang");
        throw new LangException("fileMissing", "en");
    }

    #[Depends('testBaseClass')]
    public function testBaseClassWithUnknownCode(): void {
        $this->assertException("uncoded");
        throw new Exception("testThisExceptionMessageDoesNotExist");
    }

    #[Depends('testBaseClassWithUnknownCode')]
    public function testDerivedClassWithMissingMessage(): void {
        $this->assertException("uncoded");
        throw new LangException("testThisExceptionMessageDoesNotExist");
    }

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

    public function testGetNamedExceptionParam(): void {
        $e = new LangException("stringMissing", ['msgID' => "OOK"]);
        $this->assertSame("OOK", $e->getParam("msgID"));
    }
}
