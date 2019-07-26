<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

class Context extends ExclusionContext {
    /** @var ExclusionContext */
    public $not;
    public $limit = 0;
    public $offset = 0;
    public $unread;
    public $starred;
    public $labelled;
    public $annotated;

    public function __construct() {
        $this->not = new ExclusionContext($this);
    }

    public function __clone() {
        // clone the exclusion context as well
        $this->not = clone $this->not;
    }

    /** @codeCoverageIgnore */
    public function __destruct() {
        unset($this->not);
    }

    public function limit(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function offset(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function unread(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function starred(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelled(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function annotated(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
