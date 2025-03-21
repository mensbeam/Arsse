<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

class ExclusionContext extends AbstractContext {
    use ExclusionMembers;

    public function __construct(?Context $parent = null) {
        $this->parent = $parent;
    }

    public function __clone() {
        // if the context was cloned because its parent was cloned, change the parent to the clone
        if ($this->parent) {
            $t = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1];
            if (($t['object'] ?? null) instanceof Context && $t['function'] === "__clone") {
                $this->parent = $t['object'];
            }
        }
    }

    /** @codeCoverageIgnore */
    public function __destruct() {
        unset($this->parent);
    }
}
