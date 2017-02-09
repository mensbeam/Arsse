<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLang extends \PHPUnit\Framework\TestCase {
	use TestingHelpers;

	static $vfs;
	static $path;
	static $files;

	static function setUpBeforeClass() {
		Lang\Exception::$test = true;
		self::$files = [
			'en.php'    => '<?php return ["Test.presentText" => "and the Philosopher\'s Stone"];',
			'en-ca.php' => '<?php return [];',
			'en-us.php' => '<?php return ["Test.presentText" => "and the Sorcerer\'s Stone"];',
			'fr.php'    => '<?php return ["Test.presentText" => "à l\'école des sorciers"];',
			'ja.php'    => '<?php return ["Test.absentText"  => "賢者の石"];',
			'de.php'    => '<?php return ["Test.presentText" => "und der Stein der Weisen"];',
			// corrupt files
			'it.php'    => '<?php return 0;',
			'zh.php'    => '<?php return 0',
			'ko.php'    => 'DEAD BEEF',
			'fr-ca.php' => '',
			// unreadable file
			'ru.php'    => '',
		];
		self::$vfs = vfsStream::setup("langtest", 0777, self::$files);
		self::$path = self::$vfs->url();
		// set up a file without read access
		chmod(self::$path."/ru.php", 0000);
	}

	static function tearDownAfterClass() {
		Lang\Exception::$test = false;
		self::$path = null;
		self::$vfs = null;
		self::$files = null;
	}

	function testList() {
		$this->assertEquals(sizeof(self::$files), sizeof(Lang::list("en", "vfs://langtest/")));
	}
}