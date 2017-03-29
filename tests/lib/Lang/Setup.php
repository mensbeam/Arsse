<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Lang;
use JKingWeb\Arsse\Lang;
use JKingWeb\Arsse\Data;
use org\bovigo\vfs\vfsStream;
use Phake;



trait Setup {
    function setUp() {
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
        $this->l = new Lang($this->path);
        // create a mock Lang object so as not to create a dependency loop
        $this->clearData(false);
        Data::$l = Phake::mock(Lang::class);
        Phake::when(Data::$l)->msg->thenReturn("");
        // call the additional setup method if it exists
        if(method_exists($this, "setUpSeries")) $this->setUpSeries();
    }

    function tearDown() {
        // verify calls to the mock Lang object
        Phake::verify(Data::$l, Phake::atLeast(0))->msg($this->isType("string"), $this->anything());
        Phake::verifyNoOtherInteractions(Data::$l);
        // clean up
        $this->clearData(true);
        // call the additional teardiwn method if it exists
        if(method_exists($this, "tearDownSeries")) $this->tearDownSeries();
    }
}