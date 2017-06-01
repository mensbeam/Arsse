<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Lang;
use Webmozart\Glob\Glob;

class TestLang extends \JKingWeb\Arsse\Lang {

    protected function globFiles(string $path): array {
        return Glob::glob($this->path."*.php");
    }
}