<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\Statement<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder */
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    protected $implementation = "SQLite 3";

    public function tearDown() {
        parent::tearDown();
        $this->interface->close();
        unset($this->interface);
    }

    protected function exec(string $q) {
        $this->interface->exec($q);
    }

    protected function makeStatement(string $q, array $types = []): array {
        return [$this->interface, $this->interface->prepare($q), $types];
    }

    protected function decorateTypeSyntax(string $value, string $type): string {
        return $value;
    }
}
