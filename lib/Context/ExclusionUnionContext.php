<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

class ExclusionUnionContext {
    use CommonMethods;

    protected $parent;
    protected $contexts;

    public function __construct(UnionContext $parent, &$contexts) {
        $this->parent = $parent;
        $this->contexts = $contexts;
    }

    /** @codeCoverageIgnore */
    public function __destruct() {
        unset($this->contexts);
        unset($this->parent);
    }

    public function __clone() {
        throw new \Exception("Union contexts cannot be cloned.");
    }

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            $explode = substr($prop, -5) === "Range";
            foreach ($this->contexts as $c) {
                if ($explode) {
                    $c->not->$prop(...$value);
                } else {
                    $c->not->$prop($value);
                }
            }
            return $this->parent;
        } else {
            return false;
        }
    }
}
