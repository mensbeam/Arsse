<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

/**
 * @covers \JKingWeb\Arsse\Db\PDOStatement<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    protected $implementation = "PDO SQLite 3";

    public function tearDown() {
        parent::tearDown();
        unset($this->interface);
    }

    protected function exec(string $q) {
        $this->interface->exec($q);
    }

    protected function makeStatement(string $q, array $types = []): array {
        return [$this->interface, $this->interface->prepare($q), $types];
    }

    protected function decorateTypeSyntax(string $value, string $type): string {
        if ($type=="float") {
            return (substr($value, -2)==".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
        } else {
            return $value;
        }
    }
}
