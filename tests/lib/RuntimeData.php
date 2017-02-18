<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test;

class RuntimeData extends \JKingWeb\NewsSync\RuntimeData {
    public $conf;
    public $db;
    public $user;

    public function __construct(\JKingWeb\NewsSync\Conf $conf = null) {
        $this->conf = $conf;
    }
}