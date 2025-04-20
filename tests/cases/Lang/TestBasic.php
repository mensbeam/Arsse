<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Lang;

use JKingWeb\Arsse\Lang as TestClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;

#[CoversClass(\JKingWeb\Arsse\Lang::class)]
class TestBasic extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\Test\Lang\Setup;

    public $files;
    public $path;
    public $l;

    public function testListLanguages(): void {
        $this->assertCount(sizeof($this->files), $this->l->list("en"));
    }

    #[Depends('testListLanguages')]
    public function testSetLanguage(): void {
        $this->assertEquals("en", $this->l->set("en"));
        $this->assertEquals("en_ca", $this->l->set("en_ca"));
        $this->assertEquals("de", $this->l->set("de_ch"));
        $this->assertEquals("en", $this->l->set("en_gb_hixie"));
        $this->assertEquals("en_ca", $this->l->set("en_ca_jking"));
        $this->assertEquals("en", $this->l->set("es"));
        $this->assertEquals("", $this->l->set(""));
    }

    #[Depends('testSetLanguage')]
    public function testLoadInternalStrings(): void {
        $exp = (new \ReflectionClassConstant(TestClass::class, "REQUIRED"))->getValue();
        $this->assertEquals("", $this->l->set("", true));
        $this->assertCount(sizeof($exp), $this->l->dump());
    }

    #[Depends('testLoadInternalStrings')]
    public function testLoadDefaultLanguage(): void {
        $this->assertEquals(TestClass::DEFAULT, $this->l->set(TestClass::DEFAULT, true));
        $str = $this->l->dump();
        $this->assertArrayHasKey('Exception.JKingWeb/Arsse/Exception.uncoded', $str);
        $this->assertArrayHasKey('Test.presentText', $str);
    }

    #[Depends('testLoadDefaultLanguage')]
    public function testLoadSupplementaryLanguage(): void {
        $this->l->set(TestClass::DEFAULT, true);
        $this->assertEquals("ja", $this->l->set("ja", true));
        $str = $this->l->dump();
        $this->assertArrayHasKey('Exception.JKingWeb/Arsse/Exception.uncoded', $str);
        $this->assertArrayHasKey('Test.presentText', $str);
        $this->assertArrayHasKey('Test.absentText', $str);
    }
}
