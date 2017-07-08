<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

class Transaction {
    protected $index;
    protected $pending = false;
    protected $drv;

    function __construct(Driver $drv, bool $lock = false) {
        $this->index = $drv->savepointCreate($lock);
        $this->drv = $drv;
        $this->pending = true;
    }

    function __destruct() {
        if($this->pending) {
            try {
                $this->drv->savepointUndo($this->index);
            } catch(\Throwable $e) {
                // do nothing
            }
        }
    }

    function commit(): bool {
        $out = $this->drv->savepointRelease($this->index);
        $this->pending = false;
        return $out;
    }

    function rollback(): bool {
        $out = $this->drv->savepointUndo($this->index);
        $this->pending = false;
        return $out;
    }

    function getIndex(): int {
        return $this->index;
    }

    function isPending(): bool {
        return $this->pending;
    }
}