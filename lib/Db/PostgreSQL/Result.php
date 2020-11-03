<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

class Result extends \JKingWeb\Arsse\Db\AbstractResult {
    protected $db;
    protected $r;
    protected $cur;
    protected $blobs = [];

    // actual public methods

    public function changes(): int {
        return pg_affected_rows($this->r);
    }

    public function lastId(): int {
        if ($r = @pg_query($this->db, "SELECT lastval()")) {
            return (int) pg_fetch_result($r, 0, 0);
        } else {
            return 0;
        }
    }

    // constructor/destructor

    public function __construct($db, $result) {
        $this->db = $db;
        $this->r = $result;
        for ($a = 0, $stop = pg_num_fields($result); $a < $stop; $a++) {
            if (pg_field_type($result, $a) === "bytea") {
                $this->blobs[$a] = pg_field_name($result, $a);
            }
        }
    }

    public function __destruct() {
        pg_free_result($this->r);
        unset($this->r, $this->db);
    }

    // PHP iterator methods

    public function valid() {
        $this->cur = pg_fetch_row($this->r, null, \PGSQL_ASSOC);
        if ($this->cur !== false) {
            foreach($this->blobs as $f) {
                $this->cur[$f] = hex2bin(substr($this->cur[$f], 2));
            }
            return true;
        }
        return false;
    }
}
