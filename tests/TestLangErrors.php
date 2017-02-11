<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestLangErrors extends \PHPUnit\Framework\TestCase {
	use TestingHelpers, LanguageTestingHelpers;

	static $vfs;
	static $path;
	static $files;
	static $defaultPath;

	function setUp() {
		Lang::set("", true);
	}

	function testLoadFileEmpty() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("fr_ca", true);
	}

	function testLoadFileNotAnArray() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("it", true);
	}

	function testLoadFileNotPhp() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("ko", true);
	}

	function testLoadFileCorrupt() {
		$this->assertException("fileCorrupt", "Lang");
		Lang::set("zh", true);
	}

	function testLoadFileUnreadable() {
		$this->assertException("fileUnreadable", "Lang");
		Lang::set("ru", true);
	}

	function testLoadDefaultMissing() {
		// this should be the last test of the series
		unlink(self::$path.Lang::DEFAULT.".php");
		$this->assertException("defaultFileMissing", "Lang");
		Lang::set("fr", true);
	}
}