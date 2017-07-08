<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use org\bovigo\vfs\vfsStream;


class TestLangComplex extends Test\AbstractTest {
    use Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    function setUpSeries() {
        $this->l->set(Lang::DEFAULT, true);
    }

    function testLazyLoad() {
        $this->l->set("ja");
        $this->assertArrayNotHasKey('Test.absentText', $this->l->dump());
    }

    /**
     * @depends testLazyLoad
     */
    function testGetWantedAndLoadedLocale() {
        $this->l->set("en", true);
        $this->l->set("ja");
        $this->assertEquals("ja", $this->l->get());
        $this->assertEquals("en", $this->l->get(true));
    }

    function testLoadCascadeOfFiles() {
        $this->l->set("ja", true);
        $this->assertEquals("de", $this->l->set("de", true));
        $str = $this->l->dump();
        $this->assertArrayNotHasKey('Test.absentText', $str);
        $this->assertEquals('und der Stein der Weisen', $str['Test.presentText']);
    }

    /**
     * @depends testLoadCascadeOfFiles
     */
    function testLoadSubtag() {
        $this->assertEquals("en_ca", $this->l->set("en_ca", true));
    }

    function testFetchAMessage() {
        $this->l->set("de", true);
        $this->assertEquals('und der Stein der Weisen', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    function testFetchAMessageWithMissingParameters() {
        $this->l->set("en_ca", true);
        $this->assertEquals('{0} and {1}', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    function testFetchAMessageWithSingleNumericParameter() {
        $this->l->set("en_ca", true);
        $this->assertEquals('Default language file "en" missing', $this->l->msg('Exception.JKingWeb/Arsse/Lang/Exception.defaultFileMissing', Lang::DEFAULT));
    }

    /**
     * @depends testFetchAMessage
     */
    function testFetchAMessageWithMultipleNumericParameters() {
        $this->l->set("en_ca", true);
        $this->assertEquals('Happy Rotter and the Philosopher\'s Stone', $this->l->msg('Test.presentText', ['Happy Rotter', 'the Philosopher\'s Stone']));
    }

    /**
     * @depends testFetchAMessage
     */
    function testFetchAMessageWithNamedParameters() {
        $this->assertEquals('Message string "Test.absentText" missing from all loaded language files (en)', $this->l->msg('Exception.JKingWeb/Arsse/Lang/Exception.stringMissing', ['msgID' => 'Test.absentText', 'fileList' => 'en']));
    }

    /**
     * @depends testFetchAMessage
     */
    function testReloadDefaultStrings() {
        $this->l->set("de", true);
        $this->l->set("en", true);
        $this->assertEquals('and the Philosopher\'s Stone', $this->l->msg('Test.presentText'));
    }

    /**
     * @depends testFetchAMessage
     */
    function testReloadGeneralTagAfterSubtag() {
        $this->l->set("en", true);
        $this->l->set("en_us", true);
        $this->assertEquals('and the Sorcerer\'s Stone', $this->l->msg('Test.presentText'));
        $this->l->set("en", true);
        $this->assertEquals('and the Philosopher\'s Stone', $this->l->msg('Test.presentText'));
    }
}