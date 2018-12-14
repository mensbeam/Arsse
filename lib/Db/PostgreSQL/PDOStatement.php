<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

class PDOStatement extends Statement {
    use \JKingWeb\Arsse\Db\PDOError;

    protected $db;
    protected $st;
    protected $qOriginal;
    protected $qMunged;
    protected $bindings;

    public function __construct(\PDO $db, string $query, array $bindings = []) {
        $this->db = $db;
        $this->qOriginal = $query;
        $this->retypeArray($bindings);
    }

    public function __destruct() {
        unset($this->db, $this->st);
    }

    public function retypeArray(array $bindings, bool $append = false): bool {
        if ($append) {
            return parent::retypeArray($bindings, $append);
        } else {
            $this->bindings = $bindings;
            parent::retypeArray($bindings, $append);
            $this->qMunged = self::mungeQuery($this->qOriginal, $this->types, false);
            try {
                // statement creation with PostgreSQL should never fail (it is not evaluated at creation time)
                $s = $this->db->prepare($this->qMunged);
            } catch (\PDOException $e) { // @codeCoverageIgnore
                list($excClass, $excMsg, $excData) = $this->exceptionBuild(true); // @codeCoverageIgnore
                throw new $excClass($excMsg, $excData); // @codeCoverageIgnore
            }
            $this->st = new \JKingWeb\Arsse\Db\PDOStatement($this->db, $s, $this->bindings);
        }
        return true;
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        return $this->st->runArray($values);
    }

    /** @codeCoverageIgnore */
    protected function bindValue($value, string $type, int $position): bool {
        // stub required by abstract parent, but never used
        return true;
    }
}
