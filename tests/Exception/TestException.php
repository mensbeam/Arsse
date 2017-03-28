<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;


class TestException extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    function setUp() {
        $this->clearData(false);
        $m = $this->getMockBuilder(Lang::class)->setMethods(['__invoke'])->getMock();
        $m->expects($this->any())->method("__invoke")->with($this->anything(), $this->anything())->will($this->returnValue(""));
        Data::$l = $m;
    }

    function tearDown() {
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
