<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLangComplex extends \PHPUnit\Framework\TestCase {
	use Test\Tools, Test\Lang\Setup;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	function setUp() {
		Lang::set(Lang::DEFAULT, true);
	}

	function testLazyLoad() {
		Lang::set("ja");
		$this->assertArrayNotHasKey('Test.absentText', Lang::dump());
	}
	
	function testLoadCascadeOfFiles() {
		Lang::set("ja", true);
		$this->assertEquals("de", Lang::set("de", true));
		$str = Lang::dump();
		$this->assertArrayNotHasKey('Test.absentText', $str);
		$this->assertEquals('und der Stein der Weisen', $str['Test.presentText']);
	}

	/**
     * @depends testLoadCascadeOfFiles
     */
	function testLoadSubtag() {
		$this->assertEquals("en_ca", Lang::set("en_ca", true));
	}
	
	function testFetchAMessage() {
		Lang::set("de", true);
		$this->assertEquals('und der Stein der Weisen', Lang::msg('Test.presentText'));
	}

	/**
     * @depends testFetchAMessage
     */
	function testFetchAMessageWithSingleNumericParameter() {
		Lang::set("en_ca", true);
		$this->assertEquals('Default language file "en" missing', Lang::msg('Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing', Lang::DEFAULT));
	}

	/**
     * @depends testFetchAMessage
     */
	function testFetchAMessageWithMultipleNumericParameters() {
		Lang::set("en_ca", true);
		$this->assertEquals('Happy Rotter and the Philosopher\'s Stone', Lang::msg('Test.presentText', ['Happy Rotter', 'the Philosopher\'s Stone']));
	}

	/**
     * @depends testFetchAMessage
     */
	function testFetchAMessageWithNamedParameters() {
		$this->assertEquals('Message string "Test.absentText" missing from all loaded language files (en)', Lang::msg('Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing', ['msgID' => 'Test.absentText', 'fileList' => 'en']));
	}

	/**
     * @depends testFetchAMessage
     */
	function testReloadDefaultStrings() {
		Lang::set("de", true);
		Lang::set("en", true);
		$this->assertEquals('and the Philosopher\'s Stone', Lang::msg('Test.presentText'));
	}

	/**
     * @depends testFetchAMessage
     */
	function testReloadGeneralTagAfterSubtag() {
		Lang::set("en", true);
		Lang::set("en_us", true);
		$this->assertEquals('and the Sorcerer\'s Stone', Lang::msg('Test.presentText'));
		Lang::set("en", true);
		$this->assertEquals('and the Philosopher\'s Stone', Lang::msg('Test.presentText'));
	}
}