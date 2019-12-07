<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\Result<extended>
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQL;

    protected static $createMeta = "CREATE TABLE arsse_meta(key text primary key not null, value text)";
    protected static $createTest = "CREATE TABLE arsse_test(id bigserial primary key)";

    protected function makeResult(string $q): array {
        $set = pg_query(static::$interface, $q);
        return [static::$interface, $set];
    }

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            static::dbRaze(static::$interface);
            @pg_close(static::$interface);
            static::$interface = null;
        }
        parent::tearDownAfterClass();
    }
}
