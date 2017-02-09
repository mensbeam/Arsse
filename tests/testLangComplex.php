<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLangComplex extends \PHPUnit\Framework\TestCase {
	use TestingHelpers, LanguageTestingHelpers;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	function setUp() {
		Lang::set(Lang::DEFAULT, true);
	}

	function testLoadLazy() {
		Lang::set("ja");
		$this->assertArrayNotHasKey('Test.absentText', Lang::dump());
	}
	
	function testLoadCascade() {
		Lang::set("ja", true);
		$this->assertEquals("de", Lang::set("de", true));
		$str = Lang::dump();
		$this->assertArrayNotHasKey('Test.absentText', $str);
		$this->assertEquals('und der Stein der Weisen', $str['Test.presentText']);
	}

	/**
     * @depends testLoadCascade
     */
	function testLoadSubtag() {
		$this->assertEquals("en_ca", Lang::set("en_ca", true));
	}
	
	/**
     * @depends testLoadSubtag
     */
	function testMessage() {
		Lang::set("de", true);
		$this->assertEquals('und der Stein der Weisen', Lang::msg('Test.presentText'));
	}

	/**
     * @depends testMessage
     */
	function testMessageNumMSingle() {
		Lang::set("en_ca", true);
		$this->assertEquals('Default language file "en" missing', Lang::msg('Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing', Lang::DEFAULT));
	}

	/**
     * @depends testMessage
     */
	function testMessageNumMulti() {
		Lang::set("en_ca", true);
		$this->assertEquals('Happy Rotter and the Philosopher\'s Stone', Lang::msg('Test.presentText', ['Happy Rotter', 'the Philosopher\'s Stone']));
	}

	/**
     * @depends testMessage
     */
	function testMessageNamed() {
		$this->assertEquals('Message string "Test.absentText" missing from all loaded language files (en)', Lang::msg('Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing', ['msgID' => 'Test.absentText', 'fileList' => 'en']));
	}
}