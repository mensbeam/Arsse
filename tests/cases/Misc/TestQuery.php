<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\Query;

/** @covers \JKingWeb\Arsse\Misc\Query */
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

    public function testQueryWithCommonTableExpression(): void {
        $q = (new Query("select * from table where a in (select * from cte where a = ?)", "int", 1))->setCTE("cte", "select * from other_table where a = ? and b = ?", ["str", "str"], [2, 3]);
        $this->assertSame("WITH RECURSIVE cte as (select * from other_table where a = ? and b = ?) select * from table where a in (select * from cte where a = ?)", $q->getQuery());
        $this->assertSame(["str", "str", "int"], $q->getTypes());
        $this->assertSame([2, 3, 1], $q->getValues());
        // multiple CTEs
        $q = (new Query("select * from table where a in (select * from cte1 join cte2 using (a) where a = ?)", "int", 1))->setCTE("cte1", "select * from other_table where a = ? and b = ?", ["str", "str"], [2, 3])->setCTE("cte2", "select * from other_table where c between ? and ?", ["datetime", "datetime"], [4, 5]);
        $this->assertSame("WITH RECURSIVE cte1 as (select * from other_table where a = ? and b = ?), cte2 as (select * from other_table where c between ? and ?) select * from table where a in (select * from cte1 join cte2 using (a) where a = ?)", $q->getQuery());
        $this->assertSame(["str", "str", "datetime", "datetime", "int"], $q->getTypes());
        $this->assertSame([2, 3, 4, 5, 1], $q->getValues());
    }

    public function testQueryWithPushedCommonTableExpression(): void {
        $q = (new Query("select * from table1"))->setWhere("a between ? and ?", ["datetime", "datetime"], [1, 2])
            ->setCTE("cte1", "select * from table2 where a = ? and b = ?", ["str", "str"], [3, 4])
            ->pushCTE("cte2")
            ->setBody("select * from table3 join cte1 using (a) join cte2 using (a) where a = ?", "int", 5);
        $this->assertSame("WITH RECURSIVE cte1 as (select * from table2 where a = ? and b = ?), cte2 as (select * from table1 WHERE a between ? and ?) select * from table3 join cte1 using (a) join cte2 using (a) where a = ?", $q->getQuery());
        $this->assertSame(["str", "str", "datetime", "datetime", "int"], $q->getTypes());
        $this->assertSame([3, 4, 1, 2, 5], $q->getValues());
    }

    public function testComplexQuery(): void {
        $q = (new query("select *, ? as const from table", "datetime", 1))
            ->setWhereNot("b = ?", "bool", 2)
            ->setGroup("col1", "col2")
            ->setWhere("a = ?", "str", 3)
            ->setLimit(4, 5)
            ->setOrder("col3")
            ->setCTE("cte", "select ? as const", "int", 6);
        $this->assertSame("WITH RECURSIVE cte as (select ? as const) select *, ? as const from table WHERE a = ? AND NOT (b = ?) GROUP BY col1, col2 ORDER BY col3 LIMIT 4 OFFSET 5", $q->getQuery());
        $this->assertSame(["int", "datetime", "str", "bool"], $q->getTypes());
        $this->assertSame([6, 1, 3, 2], $q->getValues());
    }
}
