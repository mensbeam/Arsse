<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

use JKingWeb\Arsse\Misc\Date;

class Context extends ExclusionContext {
    public $not;
    public $reverse = false;
    public $limit = 0;
    public $offset = 0;
    public $unread;
    public $starred;
    public $labelled;
    public $annotated;
    public $oldestArticle;
    public $latestArticle;
    public $oldestEdition;
    public $latestEdition;
    public $modifiedSince;
    public $notModifiedSince;
    public $markedSince;
    public $notMarkedSince;

    public function __construct() {
        $this->not = new ExclusionContext($this);
    }

    public function __clone() {
        // clone the exclusion context as well
        $this->not = clone $this->not;
    }

    public function __destruct() {
        unset($this->not);
    }

    public function reverse(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
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

    public function latestArticle(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function oldestArticle(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function latestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function oldestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function modifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function notModifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function markedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function notMarkedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
