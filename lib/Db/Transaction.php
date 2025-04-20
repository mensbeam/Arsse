<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db;

class Transaction {
    protected $index;
    protected $pending = false;
    protected $drv;

    public function __construct(Driver $drv, bool $lock = false) {
        $this->index = $drv->savepointCreate($lock);
        $this->drv = $drv;
        $this->pending = true;
    }

    public function __destruct() {
        if ($this->pending) {
            try {
                $this->drv->savepointUndo($this->index);
            } catch (\Throwable $e) {
                // do nothing
            }
        }
    }

    public function commit(): bool {
        $out = $this->drv->savepointRelease($this->index);
        $this->pending = false;
        return $out;
    }

    public function rollback(): bool {
        $out = $this->drv->savepointUndo($this->index);
        $this->pending = false;
        return $out;
    }

    public function getIndex(): int {
        return $this->index;
    }

    public function isPending(): bool {
        return $this->pending;
    }
}
