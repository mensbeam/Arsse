<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

trait PDODriver {
    use PDOError;

    public function exec(string $query): bool {
        try {
            $this->db->exec($query);
            return true;
        } catch (\PDOException $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
    }

    public function query(string $query): Result {
        try {
            $r = $this->db->query($query);
        } catch (\PDOException $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $r->rowCount();
        try {
            $lastId = 0;
            $lastId = $this->db->lastInsertId();
        } catch (\PDOException $e) { // @codeCoverageIgnore
        }
        return new PDOResult($r, [$changes, $lastId]);
    }

    public function prepareArray(string $query, array $paramTypes): Statement {
        try {
            $s = $this->db->prepare($query);
        } catch (\PDOException $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        return new PDOStatement($this->db, $s, $paramTypes);
    }
}