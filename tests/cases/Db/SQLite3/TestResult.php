<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

use JKingWeb\Arsse\Test\DatabaseInformation;

/** 
 * @covers \JKingWeb\Arsse\Db\SQLite3\Result<extended> 
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    protected $implementation = "SQLite 3";

    public function tearDown() {
        parent::tearDown();
        $this->interface->close();
        unset($this->interface);
    }

    protected function exec(string $q) {
        $this->interface->exec($q);
    }

    protected function makeResult(string $q): array {
        $set = $this->interface->query($q);
        $rows = $this->interface->changes();
        $id = $this->interface->lastInsertRowID();
        return [$set, [$rows, $id]];
    }
}
