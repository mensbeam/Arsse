<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLangComplex extends \PHPUnit\Framework\TestCase {
	use TestingHelpers;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	static function setUpBeforeClass() {
		// this is required to keep from having exceptions in Lang::msg() in turn calling Lang::msg() and looping
		Lang\Exception::$test = true;
		// test files
		self::$files = [
			'en.php'    => '<?php return ["Test.presentText" => "and the Philosopher\'s Stone"];',
			'en_ca.php' => '<?php return ["Test.presentText" => "{0} and {1}"];',
			'en_us.php' => '<?php return ["Test.presentText" => "and the Sorcerer\'s Stone"];',
			'fr.php'    => '<?php return ["Test.presentText" => "à l\'école des sorciers"];',
			'ja.php'    => '<?php return ["Test.absentText"  => "賢者の石"];',
			'de.php'    => '<?php return ["Test.presentText" => "und der Stein der Weisen"];',
			// corrupt files
			'it.php'    => '<?php return 0;',
			'zh.php'    => '<?php return 0',
			'ko.php'    => 'DEAD BEEF',
			'fr_ca.php' => '',
			// unreadable file
			'ru.php'    => '',
		];
		self::$vfs = vfsStream::setup("langtest", 0777, self::$files);
		self::$path = self::$vfs->url();
		// set up a file without read access
		chmod(self::$path."/ru.php", 0000);
		// make the Lang class use the vfs files
		self::$defaultPath = Lang::$path;
		Lang::$path = self::$path."/";
	}

	static function tearDownAfterClass() {
		Lang\Exception::$test = false;
		Lang::$path = self::$defaultPath;
		self::$path = null;
		self::$vfs = null;
		self::$files = null;
		Lang::set("", true);
		Lang::set(Lang::DEFAULT, true);
	}

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