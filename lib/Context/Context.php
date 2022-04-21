<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

class Context extends AbstractContext {
    use RootMembers;
    use BooleanMembers;
    use ExclusionMembers;

    /** @var ExclusionContext */
    public $not;

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
}
