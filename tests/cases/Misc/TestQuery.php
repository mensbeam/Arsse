<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\Query;
use JKingWeb\Arsse\Misc\QueryFilter;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Misc\Query::class)]
#[CoversClass(\JKingWeb\Arsse\Misc\QueryFilter::class)]
class TestQuery extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testBasicQuery(): void {
        $q = new Query("select * from table where a = ?", "int", 3);
        $this->assertSame("select * from table where a = ?", $q->getQuery());
        $this->assertSame(["int"], $q->getTypes());
        $this->assertSame([3], $q->getValues());
    }

    public function testWhereQuery(): void {
        // simple where clause
        $q = (new Query("select * from table"))->setWhere("a = ?", "int", 3);
        $this->assertSame("select * from table WHERE a = ?", $q->getQuery());
        $this->assertSame(["int"], $q->getTypes());
        $this->assertSame([3], $q->getValues());
        // compound where clause
        $q = (new Query("select * from table"))->setWhere("a = ?", "int", 3)->setWhere("b = ?", "str", 4);
        $this->assertSame("select * from table WHERE a = ? AND b = ?", $q->getQuery());
        $this->assertSame(["int", "str"], $q->getTypes());
        $this->assertSame([3, 4], $q->getValues());
        // negative where clause
        $q = (new Query("select * from table"))->setWhereNot("a = ?", "int", 3);
        $this->assertSame("select * from table WHERE NOT (a = ?)", $q->getQuery());
        $this->assertSame(["int"], $q->getTypes());
        $this->assertSame([3], $q->getValues());
        // compound negative where clause
        $q = (new Query("select * from table"))->setWhereNot("a = ?", "int", 3)->setWhereNot("b = ?", "str", 4);
        $this->assertSame("select * from table WHERE NOT (a = ? OR b = ?)", $q->getQuery());
        $this->assertSame(["int", "str"], $q->getTypes());
        $this->assertSame([3, 4], $q->getValues());
        // mixed where clause
        $q = (new Query("select * from table"))->setWhereNot("a = ?", "int", 1)->setWhere("b = ?", "str", 2)->setWhereNot("c = ?", "int", 3)->setWhere("d = ?", "str", 4);
        $this->assertSame("select * from table WHERE b = ? AND d = ? AND NOT (a = ? OR c = ?)", $q->getQuery());
        $this->assertSame(["str", "str", "int", "int"], $q->getTypes());
        $this->assertSame([2, 4, 1, 3], $q->getValues());
    }

    public function testGroupedQuery(): void {
        $q = (new Query("select col1, col2, count(*) as count from table"))->setGroup("col1", "col2");
        $this->assertSame("select col1, col2, count(*) as count from table GROUP BY col1, col2", $q->getQuery());
        $this->assertSame([], $q->getTypes());
        $this->assertSame([], $q->getValues());
    }

    public function testOrderedQuery(): void {
        $q = (new Query("select col1, col2, col3 from table"))->setOrder("col1 desc", "col2")->setOrder("col3 asc");
        $this->assertSame("select col1, col2, col3 from table ORDER BY col1 desc, col2, col3 asc", $q->getQuery());
        $this->assertSame([], $q->getTypes());
        $this->assertSame([], $q->getValues());
    }

    public function testLimitedQuery(): void {
        // no offset
        $q = (new Query("select * from table"))->setLimit(5);
        $this->assertSame("select * from table LIMIT 5", $q->getQuery());
        $this->assertSame([], $q->getTypes());
        $this->assertSame([], $q->getValues());
        // with offset
        $q = (new Query("select * from table"))->setLimit(5, 10);
        $this->assertSame("select * from table LIMIT 5 OFFSET 10", $q->getQuery());
        $this->assertSame([], $q->getTypes());
        $this->assertSame([], $q->getValues());
        // no limit with offset
        $q = (new Query("select * from table"))->setLimit(0, 10);
        $this->assertSame("select * from table LIMIT -1 OFFSET 10", $q->getQuery());
        $this->assertSame([], $q->getTypes());
        $this->assertSame([], $q->getValues());
    }

    public function testComplexQuery(): void {
        $q = (new Query("SELECT *, ? as const from table", "datetime", 1))
            ->setWhereNot("b = ?", "bool", 2)
            ->setGroup("col1", "col2")
            ->setWhere("a = ?", "str", 3)
            ->setLimit(4, 5)
            ->setOrder("col3");
        $this->assertSame("SELECT *, ? as const from table WHERE a = ? AND NOT (b = ?) GROUP BY col1, col2 ORDER BY col3 LIMIT 4 OFFSET 5", $q->getQuery());
        $this->assertSame(["datetime", "str", "bool"], $q->getTypes());
        $this->assertSame([1, 3, 2], $q->getValues());
    }

    public function testNestedWhereConditions(): void {
        $q = new Query("SELECT *, ? as const from table", "datetime", 1);
        $f = new QueryFilter;
        $f->setWhere("a = ?", "str", "ook")->setWhere("b = c")->setWhere("c = ?", "int", 42);
        $this->assertSame("a = ? AND b = c AND c = ?", (string) $f);
        $this->assertSame(["str", "int"], $f->getTypes());
        $this->assertSame(["ook", 42], $f->getValues());
        $q->setWhereGroup($f, true);
        $this->assertSame("a = ? AND b = c AND c = ?", (string) $f);
        $q->setWhereGroup($f, false);
        $this->assertSame("SELECT *, ? as const from table WHERE (a = ? AND b = c AND c = ?) AND (a = ? OR b = c OR c = ?)", $q->getQuery());
        $this->assertSame(["datetime", "str", "int", "str", "int"], $q->getTypes());
        $this->assertSame([1, "ook", 42, "ook", 42], $q->getValues());
    }
}
