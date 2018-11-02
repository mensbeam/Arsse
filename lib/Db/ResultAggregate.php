<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Db\Exception;

class ResultAggregate extends AbstractResult {
    protected $data;
    protected $index = 0;
    protected $cur = null;

    // actual public methods

    public function changes(): int {
        return array_reduce($this->data, function ($sum, $value) {
            return $sum + $value->changes();
        }, 0);
    }

    public function lastId(): int {
        return $this->data[sizeof($this->data) - 1]->lastId();
    }

    // constructor/destructor

    public function __construct(Result ...$result) {
        $this->data = $result;
    }

    public function __destruct() {
        $max = sizeof($this->data);
        for ($a = 0; $a < $max; $a++) {
            unset($this->data[$a]);
        }
    }

    // PHP iterator methods

    public function valid() {
        while (!$this->cur && isset($this->data[$this->index])) {
            $this->cur = $this->data[$this->index]->getRow();
            if (!$this->cur) {
                $this->index++;
            }
        }
        return (bool) $this->cur;
    }
}
