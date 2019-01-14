<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class Statement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use ExceptionBuilder;

    const BINDINGS = [
        "integer"   => "i",
        "float"     => "d",
        "datetime"  => "s",
        "binary"    => "b",
        "string"    => "s",
        "boolean"   => "i",
    ];

    protected $db;
    protected $st;
    protected $query;
    protected $packetSize;

    protected $values;
    protected $binds = "";

    public function __construct(\mysqli $db, string $query, array $bindings = [], int $packetSize = 4194304) {
        $this->db = $db;
        $this->query = $query;
        $this->packetSize = $packetSize;
        $this->retypeArray($bindings);
    }

    protected function prepare(string $query): bool {
        $this->st = $this->db->prepare($query);
        if (!$this->st) {
            list($excClass, $excMsg, $excData) = $this->buildEngineException($this->db->errno, $this->db->error);
            throw new $excClass($excMsg, $excData);
        }
        return true;
    }

    public function __destruct() {
        try {
            $this->st->close();
        } catch (\Throwable $e) { // @codeCoverageIgnore
        }
        unset($this->st);
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        $this->st->reset();
        // prepare values and them all at once
        $this->bindValues($values);
        if ($this->values) {
            $this->st->bind_param($this->binds, ...$this->values);
        }
        // execute the statement
        $this->st->execute();
        // clear normalized values
        $this->binds = "";
        $this->values = [];
        // check for errors
        if ($this->st->sqlstate !== "00000") {
            if ($this->st->sqlstate === "HY000") {
                list($excClass, $excMsg, $excData) = $this->buildEngineException($this->st->errno, $this->st->error);
            } else {
                list($excClass, $excMsg, $excData) = $this->buildStandardException($this->st->sqlstate, $this->st->error);
            }
            throw new $excClass($excMsg, $excData);
        }
        // create a result-set instance
        $r = $this->st->get_result();
        $changes = $this->st->affected_rows;
        $lastId = $this->st->insert_id;
        return new Result($r, [$changes, $lastId], $this);
    }

    protected function bindValue($value, string $type, int $position): bool {
        // this is a bit of a hack: we collect values (and MySQL bind types) here so that we can take 
        // advantage of the work done by bindValues() even though MySQL requires everything to be bound 
        // all at once; we also packetize large values here if necessary
        if (($type === "binary" && !is_null($value)) || (is_string($value) && strlen($value) > $this->packetSize)) {
            $this->values[] = null;
            $this->st->send_long_data($position - 1, $value);
        } else {
            $this->values[] = $value;
        }
        $this->binds .= self::BINDINGS[$type];
        return true;
    }
    public static function mungeQuery(string $query, array $types, ...$extraData): string {
        $query = explode("?", $query);
        $out = "";
        for ($b = 1; $b < sizeof($query); $b++) {
            $a = $b - 1;
            $mark = (($types[$a] ?? "") === "datetime") ? "cast(? as datetime(0))" : "?";
            $out .= $query[$a].$mark;
        }
        $out .= array_pop($query);
        return $out;
    }
}
