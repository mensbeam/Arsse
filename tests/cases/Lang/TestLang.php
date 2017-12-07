<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\Lang */
class TestLang extends Test\AbstractTest {
    use Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    public function testListLanguages() {
        $this->assertCount(sizeof($this->files), $this->l->list("en"));
    }

    /**
     * @depends testListLanguages
     */
    public function testSetLanguage() {
        $this->assertEquals("en", $this->l->set("en"));
        $this->assertEquals("en_ca", $this->l->set("en_ca"));
        $this->assertEquals("de", $this->l->set("de_ch"));
        $this->assertEquals("en", $this->l->set("en_gb_hixie"));
        $this->assertEquals("en_ca", $this->l->set("en_ca_jking"));
        $this->assertEquals("en", $this->l->set("es"));
        $this->assertEquals("", $this->l->set(""));
    }

    /**
     * @depends testSetLanguage
     */
    public function testLoadInternalStrings() {
        $this->assertEquals("", $this->l->set("", true));
        $this->assertCount(sizeof(Lang::REQUIRED), $this->l->dump());
    }

    /**
     * @depends testLoadInternalStrings
     */
    public function testLoadDefaultLanguage() {
        $this->assertEquals(Lang::DEFAULT, $this->l->set(Lang::DEFAULT, true));
        $str = $this->l->dump();
        $this->assertArrayHasKey('Exception.JKingWeb/Arsse/Exception.uncoded', $str);
        $this->assertArrayHasKey('Test.presentText', $str);
    }

    /**
     * @depends testLoadDefaultLanguage
     */
    public function testLoadSupplementaryLanguage() {
        $this->l->set(Lang::DEFAULT, true);
        $this->assertEquals("ja", $this->l->set("ja", true));
        $str = $this->l->dump();
        $this->assertArrayHasKey('Exception.JKingWeb/Arsse/Exception.uncoded', $str);
        $this->assertArrayHasKey('Test.presentText', $str);
        $this->assertArrayHasKey('Test.absentText', $str);
    }
}