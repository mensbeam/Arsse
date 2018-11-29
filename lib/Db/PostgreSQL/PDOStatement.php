<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

class PDOStatement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use \JKingWeb\Arsse\Db\PDOError;

    const BINDINGS = [
        "integer"   => "bigint",
        "float"     => "decimal",
        "datetime"  => "timestamp",
        "binary"    => "bytea",
        "string"    => "text",
        "boolean"   => "smallint", // FIXME: using boolean leads to incompatibilities with versions of SQLite bundled prior to PHP 7.3
    ];

    protected $db;
    protected $st;
    protected $qOriginal;
    protected $qMunged;
    protected $bindings;

    public function __construct(\PDO $db, string $query, array $bindings = []) {
        $this->db = $db; // both db and st are the same object due to the logic of the PDOError handler
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
                $s = $this->db->prepare($this->qMunged);
                $this->st = new \JKingWeb\Arsse\Db\PDOStatement($this->db, $s, $this->bindings);
            } catch (\PDOException $e) {
                list($excClass, $excMsg, $excData) = $this->exceptionBuild(true);
                throw new $excClass($excMsg, $excData);
            }
        }
        return true;
    }

    public static function mungeQuery(string $q, array $types, bool $mungeParamMarkers = true): string {
        $q = explode("?", $q);
        $out = "";
        for ($b = 1; $b < sizeof($q); $b++) {
            $a = $b - 1;
            $mark = $mungeParamMarkers ? "\$$b" : "?";
            $type = isset($types[$a]) ? "::".self::BINDINGS[$types[$a]] : "";
            $out .= $q[$a].$mark.$type;
        }
        $out .= array_pop($q);
        return $out;
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        return $this->st->runArray($values);
    }

    /** @codeCoverageIgnore */
    protected function bindValue($value, string $type, int $position): bool {
        // stub required by abstract parent, but never used
        return $value;
    }
}
