<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLang extends \PHPUnit\Framework\TestCase {
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

	function testList() {
		$this->assertCount(sizeof(self::$files), Lang::list("en"));
	}

	/**
     * @depends testList
     */
	function testSet() {
		$this->assertEquals("en", Lang::set("en"));
		$this->assertEquals("en_ca", Lang::set("en_ca"));
		$this->assertEquals("de", Lang::set("de_ch"));
		$this->assertEquals("en", Lang::set("en_gb_hixie"));
		$this->assertEquals("en_ca", Lang::set("en_ca_jking"));
		$this->assertEquals("en", Lang::set("es"));
		$this->assertEquals("", Lang::set(""));
	}

	/**
     * @depends testSet
     */
	function testLoadInternalStrings() {
		$this->assertEquals("", Lang::set("", true));
		$this->assertCount(sizeof(Lang::REQUIRED), Lang::dump());
	}

	/**
     * @depends testLoadInternalStrings
     */
	function testLoadDefaultStrings() {
		$this->assertEquals(Lang::DEFAULT, Lang::set(Lang::DEFAULT, true));
		$str = Lang::dump();
		$this->assertArrayHasKey('Exception.JKingWeb/NewsSync/Exception.uncoded', $str);
		$this->assertArrayHasKey('Test.presentText', $str);
	}

	/**
     * @depends testLoadDefaultStrings
     */
	function testLoadMultipleFiles() {
		Lang::set(Lang::DEFAULT, true);
		$this->assertEquals("ja", Lang::set("ja", true));
		$str = Lang::dump();
		$this->assertArrayHasKey('Exception.JKingWeb/NewsSync/Exception.uncoded', $str);
		$this->assertArrayHasKey('Test.presentText', $str);
		$this->assertArrayHasKey('Test.absentText', $str);
	}

}