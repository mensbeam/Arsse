<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

class UnionContext extends RootContext implements \ArrayAccess, \Countable, \IteratorAggregate {
    protected $contexts = [];

    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        return isset($this->contexts[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->contexts[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        assert($value instanceof RootContext, new \Exception("Union contexts may only contain other non-exclusion contexts"));
        if (isset($offset)) {
            $this->contexts[$offset] = $value;
        } else {
            $this->contexts[] = $value;
        }
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        unset($this->contexts[$offset]);
    }

    public function count(): int {
        return count($this->contexts);
    }

    public function getIterator(): \Traversable {
        foreach ($this->contexts as $k => $c) {
            yield $k => $c;
        }
    }

    public function __construct(RootContext ...$context) {
        $this->contexts = $context;
    }
}
