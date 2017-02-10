<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestException extends \PHPUnit\Framework\TestCase {
	use TestingHelpers;

	static function setUpBeforeClass() {
		Lang::set("");
	}

	static function tearDownAfterClass() {
		Lang::set(Lang::DEFAULT);
	}
	
	function testBasic() {
		$this->assertException("unknown");
		throw new Exception("unknown");
	}

	/**
     * @depends testBasic
     */
	function testPlain() {
		$this->assertException("unknown");
		throw new Exception();
	}
	
	/**
     * @depends testBasic
     */
	function testNamespace() {
		$this->assertException("fileMissing", "Lang");
		throw new Lang\Exception("fileMissing");
	}

	/**
     * @depends testNamespace
     */
	function testValues() {
		$this->assertException("fileMissing", "Lang");
		throw new Lang\Exception("fileMissing", "en");
	}
}
