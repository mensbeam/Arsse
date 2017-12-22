<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Conf;

use JKingWeb\Arsse\Conf;
use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\Conf */
class TestConf extends \JKingWeb\Arsse\Test\AbstractTest {
    public static $vfs;
    public static $path;

    public function setUp() {
        $this->clearData();
        self::$vfs = vfsStream::setup("root", null, [
            'confGood'       => '<?php return Array("lang" => "xx");',
            'confNotArray'   => '<?php return 0;',
            'confCorrupt'    => '<?php return 0',
            'confNotPHP'     => 'DEAD BEEF',
            'confEmpty'      => '',
            'confUnreadable' => '',
            'confForbidden'  => [],
        ]);
        self::$path = self::$vfs->url()."/";
        // set up a file without read or write access
        chmod(self::$path."confUnreadable", 0000);
        // set up a directory without read or write access
        chmod(self::$path."confForbidden", 0000);
    }

    public function tearDown() {
        self::$path = null;
        self::$vfs = null;
        $this->clearData();
    }

    public function testLoadDefaultValues() {
        $this->assertInstanceOf(Conf::class, new Conf());
    }

    /** @depends testLoadDefaultValues */
    public function testImportFromArray() {
        $arr = ['lang' => "xx"];
        $conf = new Conf();
        $conf->import($arr);
        $this->assertEquals("xx", $conf->lang);
    }

    /** @depends testImportFromArray */
    public function testImportFromFile() {
        $conf = new Conf();
        $conf->importFile(self::$path."confGood");
        $this->assertEquals("xx", $conf->lang);
        $conf = new Conf(self::$path."confGood");
        $this->assertEquals("xx", $conf->lang);
    }

    /** @depends testImportFromFile */
    public function testImportFromMissingFile() {
        $this->assertException("fileMissing", "Conf");
        $conf = new Conf(self::$path."confMissing");
    }

    /** @depends testImportFromFile */
    public function testImportFromEmptyFile() {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confEmpty");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWithoutReadPermission() {
        $this->assertException("fileUnreadable", "Conf");
        $conf = new Conf(self::$path."confUnreadable");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWhichIsNotAnArray() {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confNotArray");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWhichIsNotPhp() {
        $this->assertException("fileCorrupt", "Conf");
        // this should not print the output of the non-PHP file
        $conf = new Conf(self::$path."confNotPHP");
    }

    /** @depends testImportFromFile */
    public function testImportFromCorruptFile() {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confCorrupt");
    }

    public function testExportToArray() {
        $conf = new Conf();
        $conf->lang = ["en", "fr"]; // should not be exported: not scalar
        $conf->dbSQLite3File = "test.db"; // should be exported: value changed
        $conf->userDriver = null; // should be exported: changed value, even when null
        $conf->someCustomProperty = "Look at me!"; // should be exported: unknown property
        $exp = [
            'dbSQLite3File' => "test.db",
            'userDriver' => null,
            'someCustomProperty' => "Look at me!",
        ];
        $this->assertSame($exp, $conf->export());
        $res = $conf->export(true); // export all properties
        $this->assertNotSame($exp, $res);
        $this->assertArraySubset($exp, $res);
    }

    /** @depends testExportToArray
     * @depends testImportFromFile */
    public function testExportToFile() {
        $conf = new Conf();
        $conf->lang = ["en", "fr"]; // should not be exported: not scalar
        $conf->dbSQLite3File = "test.db"; // should be exported: value changed
        $conf->userDriver = null; // should be exported: changed value, even when null
        $conf->someCustomProperty = "Look at me!"; // should be exported: unknown property
        $conf->exportFile(self::$path."confNotArray");
        $arr = (include self::$path."confNotArray");
        $exp = [
            'dbSQLite3File' => "test.db",
            'userDriver' => null,
            'someCustomProperty' => "Look at me!",
        ];
        $this->assertSame($exp, $arr);
        $conf->exportFile(self::$path."confNotArray", true); // export all properties
        $arr = (include self::$path."confNotArray");
        $this->assertNotSame($exp, $arr);
        $this->assertArraySubset($exp, $arr);
    }

    public function testExportToFileWithoutWritePermission() {
        $this->assertException("fileUnwritable", "Conf");
        (new Conf)->exportFile(self::$path."confUnreadable");
    }

    public function testExportToFileWithoutCreatePermission() {
        $this->assertException("fileUncreatable", "Conf");
        (new Conf)->exportFile(self::$path."confForbidden/conf");
    }
}
