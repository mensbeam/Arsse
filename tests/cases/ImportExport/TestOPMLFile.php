<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\ImportExport\Exception;
use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\ImportExport\OPML<extended> */
class TestOPMLFile extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $vfs;
    protected $path;
    protected $opml;

    public function setUp() {
        self::clearData();
        // create a mock OPML processor with stubbed underlying import/export routines
        $this->opml = \Phake::partialMock(OPML::class);
        \Phake::when($this->opml)->export->thenReturn("OPML_FILE");
        \Phake::when($this->opml)->import->thenReturn(true);
        $this->vfs = vfsStream::setup("root", null, [
            'exportGoodFile' => "",
            'exportGoodDir'  => [],
            'exportBadFile'  => "",
            'exportBadDir'   => [],
            'importGoodFile' => "<opml/>",
            'importBadFile'  => "",
        ]);
        $this->path = $this->vfs->url()."/";
        // make the "bad" entries inaccessible
        chmod($this->path."exportBadFile", 0000);
        chmod($this->path."exportBadDir", 0000);
        chmod($this->path."importBadFile", 0000);
    }

    public function tearDown() {
        $this->path = null;
        $this->vfs = null;
        $this->opml = null;
        self::clearData();
    }

    /** @dataProvider provideFileExports */
    public function testExportOpmlToAFile(string $file, string $user, bool $flat, $exp) {
        $path = $this->path.$file;
        try {
            if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
                $this->assertException($exp);
                $this->opml->exportFile($path, $user, $flat);
            } else {
                $this->assertSame($exp, $this->opml->exportFile($path, $user, $flat));
                $this->assertSame("OPML_FILE", $this->vfs->getChild($file)->getContent());
            }
        } finally {
            \Phake::verify($this->opml)->export($user, $flat);
        }
    }

    public function provideFileExports() {
        $createException = new Exception("fileUncreatable");
        $writeException = new Exception("fileUnwritable");
        return [
            ["exportGoodFile",     "john.doe@example.com", true,  true],
            ["exportGoodFile",     "john.doe@example.com", false, true],
            ["exportGoodFile",     "jane.doe@example.com", true,  true],
            ["exportGoodFile",     "jane.doe@example.com", false, true],
            ["exportGoodDir/file", "john.doe@example.com", true,  true],
            ["exportGoodDir/file", "john.doe@example.com", false, true],
            ["exportGoodDir/file", "jane.doe@example.com", true,  true],
            ["exportGoodDir/file", "jane.doe@example.com", false, true],
            ["exportBadFile",      "john.doe@example.com", true,  $writeException],
            ["exportBadFile",      "john.doe@example.com", false, $writeException],
            ["exportBadFile",      "jane.doe@example.com", true,  $writeException],
            ["exportBadFile",      "jane.doe@example.com", false, $writeException],
            ["exportBadDir/file",  "john.doe@example.com", true,  $createException],
            ["exportBadDir/file",  "john.doe@example.com", false, $createException],
            ["exportBadDir/file",  "jane.doe@example.com", true,  $createException],
            ["exportBadDir/file",  "jane.doe@example.com", false, $createException],
        ];
    }

    /** @dataProvider provideFileImports */
    public function testImportFromOpmlFile(string $file, string $user, bool $flat, bool $replace, $exp) {
        $path = $this->path.$file;
        try {
            if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
                $this->assertException($exp);
                $this->opml->importFile($path, $user, $flat, $replace);
            } else {
                $this->assertSame($exp, $this->opml->importFile($path, $user, $flat, $replace));
            }
        } finally {
            \Phake::verify($this->opml, \Phake::times((int) ($exp === true)))->import($user, "<opml/>", $flat, $replace);
        }
    }

    public function provideFileImports() {
        $missingException = new Exception("fileMissing");
        $permissionException = new Exception("fileUnreadable");
        return [
            ["importGoodFile", "john.doe@example.com", true,  true,  true],
            ["importBadFile",  "john.doe@example.com", true,  true,  $permissionException],
            ["importNonFile",  "john.doe@example.com", true,  true,  $missingException],
            ["importGoodFile", "john.doe@example.com", true,  false, true],
            ["importBadFile",  "john.doe@example.com", true,  false, $permissionException],
            ["importNonFile",  "john.doe@example.com", true,  false, $missingException],
            ["importGoodFile", "john.doe@example.com", false, true,  true],
            ["importBadFile",  "john.doe@example.com", false, true,  $permissionException],
            ["importNonFile",  "john.doe@example.com", false, true,  $missingException],
            ["importGoodFile", "john.doe@example.com", false, false, true],
            ["importBadFile",  "john.doe@example.com", false, false, $permissionException],
            ["importNonFile",  "john.doe@example.com", false, false, $missingException],
            ["importGoodFile", "jane.doe@example.com", true,  true,  true],
            ["importBadFile",  "jane.doe@example.com", true,  true,  $permissionException],
            ["importNonFile",  "jane.doe@example.com", true,  true,  $missingException],
            ["importGoodFile", "jane.doe@example.com", true,  false, true],
            ["importBadFile",  "jane.doe@example.com", true,  false, $permissionException],
            ["importNonFile",  "jane.doe@example.com", true,  false, $missingException],
            ["importGoodFile", "jane.doe@example.com", false, true,  true],
            ["importBadFile",  "jane.doe@example.com", false, true,  $permissionException],
            ["importNonFile",  "jane.doe@example.com", false, true,  $missingException],
            ["importGoodFile", "jane.doe@example.com", false, false, true],
            ["importBadFile",  "jane.doe@example.com", false, false, $permissionException],
            ["importNonFile",  "jane.doe@example.com", false, false, $missingException],
        ];
    }
}
