<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

class RuntimeData extends \JKingWeb\Arsse\RuntimeData {
    public $conf;
    public $db;
    public $user;

    public function __construct(\JKingWeb\Arsse\Conf $conf = null) {
        $this->conf = $conf;
    }
}