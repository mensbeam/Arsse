<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

abstract class RootContext {
    use CommonProperties;
    use CommonMethods; 

    /** @var ExclusionContext */
    public $not;
    public $limit = 0;
    public $offset = 0;

    public function limit(?int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function offset(?int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
