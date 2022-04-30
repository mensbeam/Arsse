<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

abstract class AbstractContext {
    protected $props = [];
    protected $parent = null;

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            if (is_null($value)) {
                unset($this->props[$prop]);
                $this->$prop = (new \ReflectionClass($this))->getDefaultProperties()[$prop];
            } else {
                $this->props[$prop] = true;
                $this->$prop = $value;
            }
            return $this->parent ?? $this;
        } else {
            return isset($this->props[$prop]);
        }
    }
}
