<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

trait BooleanMethods {
    public function unread(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function starred(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function hidden(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelled(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function annotated(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
