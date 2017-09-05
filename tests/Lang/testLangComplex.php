<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\Lang */
class TestLangComplex extends Test\AbstractTest {
    use Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    public function setUpSeries() {
        $this->l->set(Lang::DEFAULT, true);
    }

    public function testLazyLoad() {
        $this->l->set("ja");
        $this->assertArrayNotHasKey('Test.absentText', $this->l->dump());
    }

    /**
     * @depends testLazyLoad
     */
    public function testGetWantedAndLoadedLocale() {
        $this->l->set("en", true);
        $this->l->set("ja");
        $this->assertEquals("ja", $this->l->get());
        $this->assertEquals("en", $this->l->get(true));
    }

    public function testLoadCascadeOfFiles() {
        $this->l->set("ja", true);
        $this->assertEquals("de", $this->l->set("de", true));
        $str = $this->l->dump();
        $this->assertArrayNotHasKey('Test.absentText', $str);
        $this->assertEquals('und der Stein der Weisen', $str['Test.presentText']);
    }

    /**
     * @depends testLoadCascadeOfFiles
     */
    public function testLoadSubtag() {
        $this->assertEquals("en_ca", $this->l->set("en_ca", true));
    }

    public function testFetchAMessage() {
        $this->l->set("de");
        $this->assertEquals('und der Stein der Weisen', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testFetchAMessageWithMissingParameters() {
        $this->l->set("en_ca", true);
        $this->assertEquals('{0} and {1}', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testFetchAMessageWithSingleNumericParameter() {
        $this->l->set("en_ca", true);
        $this->assertEquals('Default language file "en" missing', $this->l->msg('Exception.JKingWeb/Arsse/Lang/Exception.defaultFileMissing', Lang::DEFAULT));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testFetchAMessageWithMultipleNumericParameters() {
        $this->l->set("en_ca", true);
        $this->assertEquals('Happy Rotter and the Philosopher\'s Stone', $this->l->msg('Test.presentText', ['Happy Rotter', 'the Philosopher\'s Stone']));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testFetchAMessageWithNamedParameters() {
        $this->assertEquals('Message string "Test.absentText" missing from all loaded language files (en)', $this->l->msg('Exception.JKingWeb/Arsse/Lang/Exception.stringMissing', ['msgID' => 'Test.absentText', 'fileList' => 'en']));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testReloadDefaultStrings() {
        $this->l->set("de", true);
        $this->l->set("en", true);
        $this->assertEquals('and the Philosopher\'s Stone', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    public function testReloadGeneralTagAfterSubtag() {
        $this->l->set("en", true);
        $this->l->set("en_us", true);
        $this->assertEquals('and the Sorcerer\'s Stone', $this->l->msg('Test.presentText'));
        $this->l->set("en", true);
        $this->assertEquals('and the Philosopher\'s Stone', $this->l->msg('Test.presentText'));
    }
}
