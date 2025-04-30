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
        assert($value instanceof Context, new \Exception("Union contexts may only contain non-exclusion non-union contexts"));
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

    public function __construct(Context ...$context) {
        $this->contexts = $context;
        $this->not = new ExclusionUnionContext($this, $this->contexts);
    }

    public function __clone() {
        throw new \Exception("Union contexts cannot be cloned.");
    }

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            foreach ($this->contexts as $c) {
                $c->act($prop, $set, $value);
            }
            return $this;
        } else {
            return false;
        }
    }
}
