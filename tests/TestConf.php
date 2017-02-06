<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \org\bovigo\vfs\vfsStream;


class TestConf extends \PHPUnit\Framework\TestCase {
	use TestingHelpers;
	
	static $vfs;

	static function setUpBeforeClass() {
		$vfs = vfsStream::setup()->url();
		foreach(["confUnreadable","confGood", "confCorrupt", "confNotArray"] as $file) {
			touch($vfs."/".$file);
		}
		chmod($vfs."/confUnreadable", 0000);
		$validConf = <<<VALID_CONFIGURATION_FILE
<?php
return Array(
	"lang" => "xx"
);
VALID_CONFIGURATION_FILE;
		file_put_contents($vfs."/confGood",$validConf);
		file_put_contents($vfs."/confNotArray", "<?php return 0;");
		file_put_contents($vfs."/confCorrupt", "<?php return 0");
		file_put_contents($vfs."/confNotPHP", "DEAD BEEF");
		self::$vfs = $vfs;
	}
	
	function testConstruct() {
		$this->assertInstanceOf(Conf::class, new Conf());
	}
    
	/**
     * @depends testConstruct
     */
	function testImportArray() {
		$arr = ['lang' => "xx"];
		$conf = new Conf();
		$conf->import($arr);
		$this->assertEquals("xx", $conf->lang);
	}

	/**
     * @depends testImportArray
     */
	function testImportFile() {
		$conf = new Conf();
		$conf->importFile(self::$vfs."/confGood");
		$this->assertEquals("xx", $conf->lang);
		$conf = new Conf(self::$vfs."/confGood");
		$this->assertEquals("xx", $conf->lang);
	}

	/**
     * @depends testImportFile
     */
	function testImportFileMissing() {
		$this->assertException("fileMissing", "Conf");
		$conf = new Conf(self::$vfs."/confMissing");
	}

	/**
     * @depends testImportFile
     */
	function testImportFileUnreadable() {
		$this->assertException("fileUnreadable", "Conf");
		$conf = new Conf(self::$vfs."/confUnreadable");
	}

	/**
     * @depends testImportFile
     */
	function testImportFileNotAnArray() {
		$this->assertException("fileCorrupt", "Conf");
		$conf = new Conf(self::$vfs."/confNotArray");
	}

	/**
     * @depends testImportFile
     */
	function testImportFileNotPHP() {
		$this->assertException("fileCorrupt", "Conf");
		// this should not print the output of the non-PHP file
		$conf = new Conf(self::$vfs."/confNotPHP");
	}

	/**
     * @depends testImportFile
     */
	function testImportFileCorrupt() {
		$this->assertException("fileCorrupt", "Conf");
		// this should not print the output of the non-PHP file
		$conf = new Conf(self::$vfs."/confCorrupt");
	}
}
