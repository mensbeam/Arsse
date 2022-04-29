<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

class UnionContext extends RootContext implements \ArrayAccess, \Countable, \IteratorAggregate {
    protected $contexts = [];

    public function offsetExists(mixed $offset): bool {
        return array_key_exists($offset, $this->contexts);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->contexts[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->contexts[$offset ?? count($this->contexts)] = $value;
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

    public function __construct(Context ...$context) {
        $this->contexts = $context;
    }
}
