<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\ExclusionContext;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

/**
 * @covers \JKingWeb\Arsse\Context\Context<extended>
 * @covers \JKingWeb\Arsse\Context\ExclusionContext<extended>
 * @covers \JKingWeb\Arsse\Context\UnionContext<extended>
 */
class TestContext extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $ranges = ['modifiedRange', 'markedRange', 'articleRange', 'editionRange'];
    protected $times = ['modifiedRange', 'markedRange'];

    /** @dataProvider provideContextOptions */
    public function testSetContextOptions(string $method, array $input, $output, bool $not): void {
        $parent = new Context;
        $c = ($not) ? $parent->not : $parent;
        $default = (new \ReflectionClass($c))->getDefaultProperties()[$method];
        $this->assertFalse($c->$method(), "Context method did not initially return false");
        if (in_array($method, $this->ranges)) {
            $this->assertEquals([null, null], $c->$method, "Context property is not initially a two-member falsy array");
        } else {
            $this->assertFalse((bool) $c->$method, "Context property is not initially falsy");
        }
        $this->assertSame($parent, $c->$method(...$input), "Context method did not return the root after setting");
        $this->assertTrue($c->$method());
        if (in_array($method, $this->times)) {
            if (is_array($default)) {
                array_walk_recursive($c->$method, function(&$v, $k) {
                    if ($v !== null) {
                        $this->assertInstanceOf(\DateTimeImmutable::class, $v, "Context property contains an non-normalized date");
                    }
                    $v = ValueInfo::normalize($v, ValueInfo::T_STRING, null, "iso8601");
                });
                array_walk_recursive($output, function(&$v) {
                    $v = ValueInfo::normalize($v, ValueInfo::T_STRING, null, "iso8601");
                });
                $this->assertSame($c->$method, $output, "Context property did not return the expected results after setting");
            } else {
                $this->assertTime($c->$method, $output, "Context property did not return the expected results after setting");
            }
        } else {
            $this->assertSame($c->$method, $output, "Context property did not return the expected results after setting");
        }
        // clear the context option
        $c->$method(...array_fill(0, sizeof($input), null));
        $this->assertFalse($c->$method(), "Context method did not return false after clearing");
    }

    public function provideContextOptions(): iterable {
        $tests = [
            'limit'            => [[10],                                             10],
            'offset'           => [[5],                                              5],
            'folder'           => [[42],                                             42],
            'folders'          => [[[12,22]],                                        [12,22]],
            'folderShallow'    => [[42],                                             42],
            'foldersShallow'   => [[[0,1]],                                          [0,1]],
            'tag'              => [[44],                                             44],
            'tags'             => [[[44, 2112]],                                     [44, 2112]],
            'tagName'          => [["XLIV"],                                         "XLIV"],
            'tagNames'         => [[["XLIV", "MMCXII"]],                             ["XLIV", "MMCXII"]],
            'subscription'     => [[2112],                                           2112],
            'subscriptions'    => [[[44, 2112]],                                     [44, 2112]],
            'article'          => [[255],                                            255],
            'edition'          => [[65535],                                          65535],
            'unread'           => [[true],                                           true],
            'starred'          => [[true],                                           true],
            'hidden'           => [[true],                                           true],
            'editions'         => [[[1,2]],                                          [1,2]],
            'articles'         => [[[1,2]],                                          [1,2]],
            'label'            => [[2112],                                           2112],
            'labels'           => [[[2112, 1984]],                                   [2112, 1984]],
            'labelName'        => [["Rush"],                                         "Rush"],
            'labelNames'       => [[["Rush", "Orwell"]],                             ["Rush", "Orwell"]],
            'labelled'         => [[true],                                           true],
            'annotated'        => [[true],                                           true],
            'searchTerms'      => [[["foo", "bar"]],                                 ["foo", "bar"]],
            'annotationTerms'  => [[["foo", "bar"]],                                 ["foo", "bar"]],
            'titleTerms'       => [[["foo", "bar"]],                                 ["foo", "bar"]],
            'authorTerms'      => [[["foo", "bar"]],                                 ["foo", "bar"]],
            'modifiedRange'    => [["2020-03-06T22:08:03Z", "2022-12-31T06:33:12Z"], ["2020-03-06T22:08:03Z", "2022-12-31T06:33:12Z"]],
            'markedRange'      => [["2020-03-06T22:08:03Z", "2022-12-31T06:33:12Z"], ["2020-03-06T22:08:03Z", "2022-12-31T06:33:12Z"]],
            'articleRange'     => [[1, 100],                                         [1, 100]],
            'editionRange'     => [[1, 100],                                         [1, 100]],
        ];
        foreach ($tests as $k => $t) {
            yield $k => array_merge([$k], $t, [false]);
            if (method_exists(ExclusionContext::class, $k)) {
                yield "$k (not)" => array_merge([$k], $t, [true]);
            }
        }
    }

    public function testCleanIdArrayValues(): void {
        $methods = ["articles", "editions", "tags", "labels", "subscriptions"];
        $in = [1, "2", 3.5, 4.0, 4, "ook", 0, -20, true, false, null, new \DateTime, -1.0];
        $out = [1, 2, 4];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCleanFolderIdArrayValues(): void {
        $methods = ["folders", "foldersShallow"];
        $in = [1, "2", 3.5, 4.0, 4, "ook", 0, -20, true, false, null, new \DateTime, -1.0];
        $out = [1, 2, 4, 0];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCleanStringArrayValues(): void {
        $methods = ["searchTerms", "annotationTerms", "titleTerms", "authorTerms", "tagNames", "labelNames"];
        $now = new \DateTime;
        $in = [1, 3.0, "ook", 0, true, false, null, $now, ""];
        $out = ["1", "3", "ook", "0", valueInfo::normalize($now, ValueInfo::T_STRING)];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCleanDateRangeArrayValues(): void {
        $methods = ["modifiedRanges", "markedRanges"];
        $in = [null, 1, [1, 2, 3], [1], [null, null], ["ook", null], ["2022-09-13T06:46:28 America/Los_angeles", new \DateTime("2022-01-23T00:33:49Z")], [0, null], [null, 0]];
        $out = [[Date::normalize("2022-09-13T13:46:28Z"), Date::normalize("2022-01-23T00:33:49Z")], [Date::normalize(0), null], [null, Date::normalize(0)]];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertEquals($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCloneAContext(): void {
        $c1 = new Context;
        $c2 = clone $c1;
        $this->assertEquals($c1, $c2);
        $this->assertEquals($c1->not, $c2->not);
        $this->assertNotSame($c1, $c2);
        $this->assertNotSame($c1->not, $c2->not);
        $this->assertSame($c1, $c1->not->article(null));
        $this->assertSame($c2, $c2->not->article(null));
    }

    public function testExerciseAUnionContext(): void {
        $c1 = new UnionContext;
        $c2 = new Context;
        $c3 = new UnionContext;
        $this->assertSame(0, sizeof($c1));
        $c1[] = $c2;
        $c1[2] = $c3;
        $this->assertSame(2, sizeof($c1));
        $this->assertSame($c2, $c1[0]);
        $this->assertSame($c3, $c1[2]);
        $this->assertSame(null, $c1[1]);
        unset($c1[0]);
        $this->assertFalse(isset($c1[0]));
        $this->assertTrue(isset($c1[2]));
        $c1[] = $c2;
        $act = [];
        foreach ($c1 as $k => $v) {
            $act[$k] = $v;
        }
        $exp = [2 => $c3, $c2];
        $this->assertSame($exp, $act);
    }
}
