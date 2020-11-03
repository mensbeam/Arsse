<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PostgreSQLPDO;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\PDOResult<extended>
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQLPDO;

    protected static $createMeta = "CREATE TABLE arsse_meta(key text primary key not null, value text)";
    protected static $createTest = "CREATE TABLE arsse_test(id bigserial primary key)";
    protected static $selectBlob = "SELECT '\\xDEADBEEF'::bytea as blob";

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        return [static::$interface, $set];
    }
}
