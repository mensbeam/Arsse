<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

class Exception extends \Exception {
    protected $data = [];

    public function __construct($msg = "UNSPECIFIED_ERROR", $data = [], $e = null) {
        $this->data = $data;
        parent::__construct($msg, 0, $e);
    }

    public function getData(): array {
        $err = ['error' => $this->getMessage()];
        return array_merge($err, $this->data, $err);
    }
}
