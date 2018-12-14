<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class Statement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use Dispatch;

    const BINDINGS = [
        "integer"   => "bigint",
        "float"     => "decimal",
        "datetime"  => "timestamp(0) without time zone",
        "binary"    => "bytea",
        "string"    => "text",
        "boolean"   => "smallint", // FIXME: using boolean leads to incompatibilities with versions of SQLite bundled prior to PHP 7.3
    ];

    protected $db;
    protected $in = [];
    protected $qOriginal;
    protected $qMunged;
    protected $bindings;

    public function __construct($db, string $query, array $bindings = []) {
        $this->db = $db; 
        $this->qOriginal = $query;
        $this->retypeArray($bindings);
    }

    public function retypeArray(array $bindings, bool $append = false): bool {
        if ($append) {
            return parent::retypeArray($bindings, $append);
        } else {
            $this->bindings = $bindings;
            parent::retypeArray($bindings, $append);
            $this->qMunged = self::mungeQuery($this->qOriginal, $this->types, true);
        }
        return true;
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        $this->in = [];
        $this->bindValues($values);
        $r = $this->dispatchQuery($this->qMunged, $this->in);
        if (is_resource($r)) {
            return new Result($this->db, $r);
        } else {
            list($excClass, $excMsg, $excData) = $r;
            throw new $excClass($excMsg, $excData);
        }
    }

    protected function bindValue($value, string $type, int $position): bool {
        $this->in[] = $value;
        return true;
    }

    protected static function mungeQuery(string $q, array $types, bool $mungeParamMarkers = true): string {
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
}
