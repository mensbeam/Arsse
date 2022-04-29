<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

class UnionContext extends RootContext implements \ArrayAccess, \Countable, \IteratorAggregate {
    protected $contexts = [];

    public function offsetExists(mixed $offset): bool {
        return isset($this->contexts[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->contexts[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        assert($value instanceof RootContext, new \Exception("Union contexts may only contain other non-exclusion contexts"));
        if (isset($offset)) {
            $this->contexts[$offset] = $value;
        } else {
            $this->contexts[] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void {
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
