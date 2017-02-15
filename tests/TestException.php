<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestException extends \PHPUnit\Framework\TestCase {
	use Test\Tools;

	static function setUpBeforeClass() {
		Lang::set("");
	}

	static function tearDownAfterClass() {
		Lang::set(Lang::DEFAULT);
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
     * @depends testBaseClass
     */
	function testBaseClassWithMissingMessage() {
		$this->assertException("stringMissing", "Lang");
		throw new Exception("invalid");
	}

	/**
     * @depends testBaseClassWithUnknownCode
     */
	function testDerivedClassWithMissingMessage() {
		$this->assertException("uncoded");
		throw new Lang\Exception("testThisExceptionMessageDoesNotExist");
	}
	
}
