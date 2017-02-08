<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLang extends \PHPUnit\Framework\TestCase {
	use TestingHelpers;

	static $vfs;
	static $path;

	const FILES = [
		'en.php'    => '<?php return ["Test.presentText" => "and the Philosopher\'s Stone"];',
		'en-ca.php' => '<?php return [];',
		'en-us.php' => '<?php return ["Test.presentText" => "and the Sorcerer\'s Stone"];',
		'fr.php'    => '<?php return ["Test.presentText" => "à l\'école des sorciers"];',
		'ja.php'    => '<?php return ["Test.absentText"  => "賢者の石"];',
		// corrupt files
		'it.php'    => '<?php return 0;',
		'zh.php'    => '<?php return 0',
		'ko.php'    => 'DEAD BEEF',
		// empty file
		'fr-ca.php' => '',
		// unreadable file
		'ru.php'    => '',
	];

	static function setUpBeforeClass() {
		Lang\Exception::$test = true;
		self::$vfs = vfsStream::setup("langtest", 0777, self::FILES);
		self::$path = self::$vfs->url();
		// set up a file without read access
		chmod(self::$path."/ru.php", 0000);
	}

	static function tearDownAfterClass() {
		Lang\Exception::$test = false;
		self::$path = null;
		self::$vfs = null;
	}

	function testList() {
		$this->assertEquals(sizeof(self::FILES), sizeof(Lang::list("en", "vfs://langtest/")));
	}
}