<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

class Database extends \JKingWeb\Arsse\Database {
    public function __construct(\JKingWeb\Arsse\Db\Driver $drv) {
        $this->db = $drv;
    }
}