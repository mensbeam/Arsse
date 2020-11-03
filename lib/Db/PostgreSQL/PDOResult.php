<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

class PDOResult extends \JKingWeb\Arsse\Db\PDOResult {

    // This method exists to transparent handle byte-array results

    public function valid() {
        $this->cur = $this->set->fetch(\PDO::FETCH_ASSOC);
        if ($this->cur !== false) {
            foreach($this->cur as $k => $v) {
                if (is_resource($v)) {
                    $this->cur[$k] = stream_get_contents($v);
                    fclose($v);
                }
            }
            return true;
        }
        return false;
    }
}
