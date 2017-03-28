<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use org\bovigo\vfs\vfsStream;


class TestLangErrors extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    function setUpSeries() {
        $this->l->set("", true);
    }

    function testLoadEmptyFile() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("fr_ca", true);
    }

    function testLoadFileWhichDoesNotReturnAnArray() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("it", true);
    }

    function testLoadFileWhichIsNotPhp() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("ko", true);
    }

    function testLoadFileWhichIsCorrupt() {
        $this->assertException("fileCorrupt", "Lang");
        $this->l->set("zh", true);
    }

    function testLoadFileWithooutReadPermission() {
        $this->assertException("fileUnreadable", "Lang");
        $this->l->set("ru", true);
    }

    function testLoadSubtagOfMissingLanguage() {
        $this->assertException("fileMissing", "Lang");
        $this->l->set("pt_br", true);
    }

    function testFetchInvalidMessage() {
        $this->assertException("stringInvalid", "Lang");
        $this->l->set("vi", true);
        $txt = $this->l->msg('Test.presentText');
    }

    function testFetchMissingMessage() {
        $this->assertException("stringMissing", "Lang");
        $txt = $this->l->msg('Test.absentText');
    }

    function testLoadMissingDefaultLanguage() {
        unlink($this->path.Lang::DEFAULT.".php");
        $this->assertException("defaultFileMissing", "Lang");
        $this->l->set("fr", true);
    }
}