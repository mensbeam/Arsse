<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db\PostgreSQL;

class Statement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use Dispatch;

    protected const BINDINGS = [
        self::T_INTEGER  => "bigint",
        self::T_FLOAT    => "decimal",
        self::T_DATETIME => "timestamp(0) without time zone",
        self::T_BINARY   => "bytea",
        self::T_STRING   => "text",
        self::T_BOOLEAN  => "smallint", // NOTE: Integers are used rather than booleans so that they may be manipulated arithmetically
    ];

    protected $db;
    protected $in = [];
    protected $query;
    protected $qMunged;
    protected $bindings;

    public function __construct($db, string $query, array $bindings = []) {
        $this->db = $db;
        $this->query = $query;
        $this->retypeArray($bindings);
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        $this->in = [];
        $this->bindValues($values);
        $r = $this->dispatchQuery($this->qMunged, $this->in);
        $this->in = [];
        if (is_resource($r) || $r instanceof \PgSql\Result) { //class since PHP 8.1
            return new Result($this->db, $r);
        } else {
            [$excClass, $excMsg, $excData] = $r;
            throw new $excClass($excMsg, $excData);
        }
    }

    protected function bindValue($value, int $type, int $position): bool {
        if ($value !== null && ($this->types[$position - 1] % self::T_NOT_NULL) === self::T_BINARY) {
            $value = "\\x".bin2hex($value);
        }
        $this->in[] = $value;
        return true;
    }

    public static function mungeQuery(string $q, array $types, ...$extraData): string {
        $mungeParamMarkers = (bool) ($extraData[0] ?? true);
        $q = explode("?", $q);
        $out = "";
        for ($b = 1; $b < sizeof($q); $b++) {
            $a = $b - 1;
            $mark = $mungeParamMarkers ? "\$$b" : "?";
            $type = isset($types[$a]) ? "::".self::BINDINGS[$types[$a] % self::T_NOT_NULL] : "";
            $out .= $q[$a].$mark.$type;
        }
        $out .= array_pop($q);
        return $out;
    }

    protected function prepare(string $query): bool {
        $this->qMunged = $query;
        return true;
    }
}
