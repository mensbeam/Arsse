<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Lang;

use JKingWeb\Arsse\Lang as TestClass;

/** @covers \JKingWeb\Arsse\Lang */
class TestErrors extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    public function setUpSeries() {
        $this->l->set("", true);
    }

    public function testLoadEmptyFile() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("fr_ca", true);
    }

    public function testLoadFileWhichDoesNotReturnAnArray() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("it", true);
    }

    public function testLoadFileWhichIsNotPhp() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("ko", true);
    }

    public function testLoadFileWhichIsCorrupt() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("zh", true);
    }

    public function testLoadFileWithooutReadPermission() {
        $this->assertException("fileUnreadable", "Lang");
        $this->l->set("ru", true);
    }

    public function testLoadSubtagOfMissingLanguage() {
        $this->assertException("fileMissing", "Lang");
        $this->l->set("pt_br", true);
    }

    public function testFetchInvalidMessage() {
        $this->assertException("stringInvalid", "Lang");
        $this->l->set("vi", true);
        $txt = $this->l->msg('Test.presentText');
    }

    public function testFetchMissingMessage() {
        $this->assertException("stringMissing", "Lang");
        $txt = $this->l->msg('Test.absentText');
    }

    public function testLoadMissingDefaultLanguage() {
        unlink($this->path.TestClass::DEFAULT.".php");
        $this->assertException("defaultFileMissing", "Lang");
        $this->l->set("fr", true);
    }

    public function testLoadMissingLanguageWhenFetching() {
        $this->l->set("en_ca");
        unlink($this->path.TestClass::DEFAULT.".php");
        $this->assertException("fileMissing", "Lang");
        $this->l->msg('Test.presentText');
    }

    public function testLoadMissingDefaultLanguageWhenFetching() {
        unlink($this->path.TestClass::DEFAULT.".php");
        $this->l = new TestClass($this->path);
        $this->assertException("stringMissing", "Lang");
        $this->l->msg('Test.presentText');
    }
}
