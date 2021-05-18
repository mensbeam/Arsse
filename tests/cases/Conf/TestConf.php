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

    public function setUp(): void {
        parent::setUp();
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

    public function tearDown(): void {
        self::$path = null;
        self::$vfs = null;
        parent::tearDown();
    }

    public function testLoadDefaultValues(): void {
        $this->assertInstanceOf(Conf::class, new Conf);
    }

    /** @depends testLoadDefaultValues */
    public function testImportFromArray(): void {
        $arr = [
            'lang'       => "xx",
            'purgeFeeds' => "P2D",
        ];
        $conf = new Conf;
        $conf->import($arr);
        $this->assertEquals("xx", $conf->lang);
    }

    /** @depends testImportFromArray */
    public function testImportFromFile(): void {
        $conf = new Conf;
        $conf->importFile(self::$path."confGood");
        $this->assertEquals("xx", $conf->lang);
        $conf = new Conf(self::$path."confGood");
        $this->assertEquals("xx", $conf->lang);
    }

    /** @depends testImportFromFile */
    public function testImportFromMissingFile(): void {
        $this->assertException("fileMissing", "Conf");
        $conf = new Conf(self::$path."confMissing");
    }

    /** @depends testImportFromFile */
    public function testImportFromEmptyFile(): void {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confEmpty");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWithoutReadPermission(): void {
        $this->assertException("fileUnreadable", "Conf");
        $conf = new Conf(self::$path."confUnreadable");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWhichIsNotAnArray(): void {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confNotArray");
    }

    /** @depends testImportFromFile */
    public function testImportFromFileWhichIsNotPhp(): void {
        $this->assertException("fileCorrupt", "Conf");
        // this should not print the output of the non-PHP file
        $conf = new Conf(self::$path."confNotPHP");
    }

    /** @depends testImportFromFile */
    public function testImportFromCorruptFile(): void {
        $this->assertException("fileCorrupt", "Conf");
        $conf = new Conf(self::$path."confCorrupt");
    }

    public function testImportBogusValue(): void {
        $arr = [
            'dbAutoUpdate' => "yes, please",
        ];
        $conf = new Conf;
        $this->assertException("typeMismatch", "Conf");
        $conf->import($arr);
    }

    public function testImportCustomProperty(): void {
        $arr = [
            'customProperty' => "I'm special!",
        ];
        $conf = new Conf;
        $this->assertSame($conf, $conf->import($arr));
    }

    public function testImportBogusDriver(): void {
        $arr = [
            'dbDriver' => "this driver does not exist",
        ];
        $conf = new Conf;
        $this->assertException("semanticMismatch", "Conf");
        $conf->import($arr);
    }

    public function testExportToArray(): void {
        $conf = new Conf;
        $conf->lang = ["en", "fr"]; // should not be exported: not scalar
        $conf->dbSQLite3File = "test.db"; // should be exported: value changed
        $conf->userDriver = null; // should be exported: changed value, even when null
        $conf->serviceFrequency = new \DateInterval("PT1H"); // should be exported (as string): value changed
        $conf->someCustomProperty = "Look at me!"; // should be exported: unknown property
        $exp = [
            'dbSQLite3File'      => "test.db",
            'userDriver'         => null,
            'serviceFrequency'   => "PT1H",
            'someCustomProperty' => "Look at me!",
        ];
        $this->assertSame($exp, $conf->export());
        $res = $conf->export(true); // export all properties
        $this->assertNotSame($exp, $res);
        $this->assertArraySubset($exp, $res);
    }

    /** @depends testExportToArray
     * @depends testImportFromFile */
    public function testExportToFile(): void {
        $conf = new Conf;
        $conf->lang = ["en", "fr"]; // should not be exported: not scalar
        $conf->dbSQLite3File = "test.db"; // should be exported: value changed
        $conf->userDriver = null; // should be exported: changed value, even when null
        $conf->someCustomProperty = "Look at me!"; // should be exported: unknown property
        $conf->exportFile(self::$path."confNotArray");
        $arr = (include self::$path."confNotArray");
        $exp = [
            'dbSQLite3File'      => "test.db",
            'userDriver'         => null,
            'someCustomProperty' => "Look at me!",
        ];
        $this->assertSame($exp, $arr);
        $conf->exportFile(self::$path."confNotArray", true); // export all properties
        $arr = (include self::$path."confNotArray");
        $this->assertNotSame($exp, $arr);
        $this->assertArraySubset($exp, $arr);
    }

    /** @depends testExportToFile */
    public function testExportToStdout(): void {
        $conf = new Conf(self::$path."confGood");
        $conf->exportFile(self::$path."confGood");
        $this->expectOutputString(file_get_contents(self::$path."confGood"));
        $conf->exportFile("php://output");
    }

    public function testExportToFileWithoutWritePermission(): void {
        $this->assertException("fileUnwritable", "Conf");
        (new Conf)->exportFile(self::$path."confUnreadable");
    }

    public function testExportToFileWithoutCreatePermission(): void {
        $this->assertException("fileUncreatable", "Conf");
        (new Conf)->exportFile(self::$path."confForbidden/conf");
    }
}
