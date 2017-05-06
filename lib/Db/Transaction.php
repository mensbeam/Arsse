<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

class Transaction {
    protected $pending = false;
    protected $drv;

    function __construct(Driver $drv) {
        $drv->savepointCreate();
        $this->drv = $drv;
        $this->pending = true;
    }

    function __destruct() {
        if($this->pending) {
            try {
                $this->drv->savepointUndo();
            } catch(\Throwable $e) {
                // do nothing
            }
        }
    }

    function commit(): bool {
        if($this->pending) {
            $this->drv->savepointRelease();
            $this->pending = false;
            return true;
        } else {
            return false;
        }
    }

    function rollback(): bool {
        if($this->pending) {
            $this->drv->savepointUndo();
            $this->pending = false;
            return true;
        } else {
            return false;
        }
    }
}