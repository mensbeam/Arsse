<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
require_once __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."bootstrap.php";

use \org\bovigo\vfs\vfsStream;

trait TestingHelpers {
	function assertException(string $msg, string $prefix = "", string $type = "Exception") {
		$class = NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
		$msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
		if(array_key_exists($msgID, Exception::CODES)) {
			$code = Exception::CODES[$msgID];
		} else {
			$code = 0;
		}
		$this->expectException($class);
		$this->expectExceptionCode($code);
	}
	
	function assertExc(string $msg, string $prefix = "", string $type = "Exception") {
		$class = NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
		$msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
		if(array_key_exists($msgID, Exception::CODES)) {
			$code = Exception::CODES[$msgID];
		} else {
			$code = 0;
		}
		echo $class."\n";
		$this->expectException($class);
		$this->expectExceptionCode($code);
	}
}

trait LanguageTestingHelpers {
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
			'pt_br.php' => '<?php return ["Test.presentText" => "e a Pedra Filosofal"];',
			'vi.php'    => '<?php return [];',
			// corrupt files
			'it.php'    => '<?php return 0;',
			'zh.php'    => '<?php return 0',
			'ko.php'    => 'DEAD BEEF',
			'fr_ca.php' => '',
			// unreadable file
			'ru.php'    => '',
		];
		self::$vfs = vfsStream::setup("langtest", 0777, self::$files);
		self::$path = self::$vfs->url()."/";
		// set up a file without read access
		chmod(self::$path."ru.php", 0000);
		// make the Lang class use the vfs files
		self::$defaultPath = Lang::$path;
		Lang::$path = self::$path;
	}

	static function tearDownAfterClass() {
		Lang\Exception::$test = false;
		Lang::$path = self::$defaultPath;
		self::$path = null;
		self::$vfs = null;
		self::$files = null;
		Lang::set("", true);
		Lang::set(Lang::DEFAULT);
	}
}