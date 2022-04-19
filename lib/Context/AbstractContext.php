<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

abstract class AbstractContext {
    protected $props = [];
    protected $parent = null;

    public function __construct(self $c = null) {
        $this->parent = $c;
    }

    public function __clone() {
        // if the context was cloned because its parent was cloned, change the parent to the clone
        if ($this->parent) {
            $t = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1];
            if (($t['object'] ?? null) instanceof self && $t['function'] === "__clone") {
                $this->parent = $t['object'];
            }
        }
    }

    /** @codeCoverageIgnore */
    public function __destruct() {
        unset($this->parent);
    }

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
