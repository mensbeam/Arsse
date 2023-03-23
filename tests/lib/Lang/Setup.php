<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test\Lang;

use JKingWeb\Arsse\Lang;
use JKingWeb\Arsse\Arsse;
use org\bovigo\vfs\vfsStream;
use Webmozart\Glob\Glob;

trait Setup {
    public function setUp(): void {
        // test files
        $this->files = [
            'en.php'    => '<?php return ["Test.presentText" => "and the Philosopher\'s Stone"];',
            'en_ca.php' => '<?php return ["Test.presentText" => "{0} and {1}"];',
            'en_us.php' => '<?php return ["Test.presentText" => "and the Sorcerer\'s Stone"];',
            'fr.php'    => '<?php return ["Test.presentText" => "à l\'école des sorciers"];',
            'ja.php'    => '<?php return ["Test.absentText"  => "賢者の石"];',
            'de.php'    => '<?php return ["Test.presentText" => "und der Stein der Weisen"];',
            'pt_br.php' => '<?php return ["Test.presentText" => "e a Pedra Filosofal"];',
            // corrupted message in valid file
            'vi.php'    => '<?php return ["Test.presentText" => "{0} and {1"];',
            // corrupt files
            'it.php'    => '<?php return 0;',
            'zh.php'    => '<?php return 0',
            'ko.php'    => 'DEAD BEEF',
            'fr_ca.php' => '',
            // unreadable file
            'ru.php'    => '',
        ];
        $vfs = vfsStream::setup("langtest", 0777, $this->files);
        $this->path = $vfs->url()."/";
        // set up a file without read access
        chmod($this->path."ru.php", 0000);
        // make the test Lang class use the vfs files
        $this->l = $this->partialMock(Lang::class, $this->path);
        $this->l->globFiles->does(function(string $path): array {
            return Glob::glob($this->path."*.php");
        });
        $this->l = $this->l->get();
        // create a mock Lang object so as not to create a dependency loop
        self::clearData(false);
        Arsse::$lang = $this->mock(Lang::class);
        Arsse::$lang->msg->returns("");
        Arsse::$lang = Arsse::$lang->get();
        // call the additional setup method if it exists
        if (method_exists($this, "setUpSeries")) {
            $this->setUpSeries();
        }
    }

    public function tearDown(): void {
        // clean up
        self::clearData(true);
        // call the additional teardiwn method if it exists
        if (method_exists($this, "tearDownSeries")) {
            $this->tearDownSeries();
        }
    }
}
