<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Database;

/** @covers \JKingWeb\Arsse\Database */
class TestDatabase extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const COL_DEFS = [
        'arsse_meta' => [
            'key'   => "strict str",
            'value' => "str",
        ],
        'arsse_users' => [
            'id'       => "strict str",
            'password' => "str",
            'num'      => "strict int",
            'admin'    => "strict bool",
        ],
        'arsse_user_meta' => [
            'owner'    => "strict str",
            'key'      => "strict str",
            'modified' => "strict datetime",
            'value'    => "str",
        ],
        'arsse_sessions' => [
            'id'      => "strict str",
            'created' => "strict datetime",
            'expires' => "strict datetime",
            'user'    => "strict str",
        ],
        'arsse_tokens' => [
            'id'      => "strict str",
            'class'   => "strict str",
            'user'    => "strict str",
            'created' => "strict datetime",
            'expires' => "datetime",
            'data'    => "str",
        ],
        'arsse_feeds' => [
            'id'         => "int",
            'url'        => "strict str",
            'title'      => "str",
            'source'     => "str",
            'updated'    => "datetime",
            'modified'   => "datetime",
            'next_fetch' => "datetime",
            'orphaned'   => "datetime",
            'etag'       => "strict str",
            'err_count'  => "strict int",
            'err_msg'    => "str",
            'username'   => "strict str",
            'password'   => "strict str",
            'size'       => "strict int",
            'icon'       => "int",
        ],
        'arsse_icons' => [
            'id'         => "int",
            'url'        => "strict str",
            'modified'   => "datetime",
            'etag'       => "strict str",
            'next_fetch' => "datetime",
            'orphaned'   => "datetime",
            'type'       => "str",
            'data'       => "blob",
        ],
        'arsse_articles' => [
            'id'                 => "int",
            'feed'               => "strict int",
            'url'                => "str",
            'title'              => "str",
            'author'             => "str",
            'published'          => "datetime",
            'edited'             => "datetime",
            'modified'           => "strict datetime",
            'guid'               => "str",
            'url_title_hash'     => "strict str",
            'url_content_hash'   => "strict str",
            'title_content_hash' => "strict str",
            'content_scraped'    => "str",
            'content'            => "str",
        ],
        'arsse_editions' => [
            'id'       => "int",
            'article'  => "strict int",
            'modified' => "strict datetime",
        ],
        'arsse_enclosures' => [
            'article' => "strict int",
            'url'     => "str",
            'type'    => "str",
        ],
        'arsse_categories' => [
            'article' => "strict int",
            'name'    => "str",
        ],
        'arsse_marks' => [
            'article'      => "strict int",
            'subscription' => "strict int",
            'read'         => "strict bool",
            'starred'      => "strict bool",
            'modified'     => "datetime",
            'note'         => "strict str",
            'touched'      => "strict bool",
            'hidden'       => "strict bool",
        ],
        'arsse_subscriptions' => [
            'id'         => "int",
            'owner'      => "strict str",
            'feed'       => "strict int",
            'added'      => "strict datetime",
            'modified'   => "strict datetime",
            'title'      => "str",
            'order_type' => "strict int",
            'pinned'     => "strict bool",
            'folder'     => "int",
            'keep_rule'  => "str",
            'block_rule' => "str",
            'scrape'     => "strict bool",
        ],
        'arsse_folders' => [
            'id'       => "int",
            'owner'    => "strict str",
            'parent'   => "int",
            'name'     => "strict str",
            'modified' => "strict datetime",
        ],
        'arsse_tags' => [
            'id'       => "int",
            'owner'    => "strict str",
            'name'     => "strict str",
            'modified' => "strict datetime",
        ],
        'arsse_tag_members' => [
            'tag'          => "strict int",
            'subscription' => "strict int",
            'assigned'     => "strict bool",
            'modified'     => "strict datetime",
        ],
        'arsse_labels' => [
            'id'       => "int",
            'owner'    => "strict str",
            'name'     => "strict str",
            'modified' => "strict datetime",
        ],
        'arsse_label_members' => [
            'label'        => "strict int",
            'article'      => "strict int",
            'subscription' => "strict int",
            'assigned'     => "strict bool",
            'modified'     => "strict datetime",
        ],
    ];

    protected $db = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        try {
            $this->db = new Database;
        } catch (\JKingWeb\Arsse\Db\Exception $e) {
            $this->markTestSkipped("SQLite 3 database driver not available");
        }
    }

    public function tearDown(): void {
        $this->db = null;
        parent::tearDown();
    }

    protected function invoke(string $method, ...$arg) {
        $m = new \ReflectionMethod($this->db, $method);
        $m->setAccessible(true);
        return $m->invoke($this->db, ...$arg);
    }

    /** @dataProvider provideInClauses */
    public function testGenerateInClause(string $clause, array $values, array $inV, string $inT): void {
        $types = array_fill(0, sizeof($values), $inT);
        $exp = [$clause, $types, $values];
        $this->assertSame($exp, $this->invoke("generateIn", $inV, $inT));
    }

    public function provideInClauses(): iterable {
        $l = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue() + 1;
        $strings = array_fill(0, $l, "");
        $ints = range(1, $l);
        $longString = str_repeat("0", (new \ReflectionClassConstant(Database::class, "LIMIT_SET_STRING_LENGTH"))->getValue() + 1);
        $params = implode(",", array_fill(0, $l, "?"));
        $intList = implode(",", $ints);
        $stringList = implode(",", array_fill(0, $l, "''"));
        return [
            ["null",               [],            [],                                   "str"],
            ["?",                  [1],           [1],                                  "int"],
            ["?",                  ["1"],         ["1"],                                "int"],
            ["?,?",                [null, null],  [null, null],                         "str"],
            ["null",               [],            array_fill(0, $l, null),              "str"],
            ["$intList",           [],            $ints,                                "int"],
            ["$intList,".($l + 1),   [],            array_merge($ints, [$l + 1]),           "int"],
            ["$intList,0",         [],            array_merge($ints, ["OOK"]),          "int"],
            ["$intList",           [],            array_merge($ints, [null]),           "int"],
            ["$stringList,''",     [],            array_merge($strings, [""]),          "str"],
            ["$stringList",        [],            array_merge($strings, [null]),        "str"],
            ["$stringList,?",      [$longString], array_merge($strings, [$longString]), "str"],
            ["$stringList,'A''s'", [],            array_merge($strings, ["A's"]),       "str"],
            ["$stringList,?",      ["???"],       array_merge($strings, ["???"]),       "str"],
            ["$params",            $ints,         $ints,                                "bool"],
        ];
    }

    /** @dataProvider provideSearchClauses */
    public function testGenerateSearchClause(string $clause, array $values, array $inV, array $inC, bool $inAny): void {
        // this is not an exhaustive test; integration tests already cover the ins and outs of the functionality
        $types = array_fill(0, sizeof($values), "str");
        $exp = [$clause, $types, $values];
        $this->assertSame($exp, $this->invoke("generateSearch", $inV, $inC, $inAny));
    }

    public function provideSearchClauses(): iterable {
        $setSize = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue();
        $terms = array_fill(0, $setSize + 1, "a");
        $clause = array_fill(0, $setSize + 1, "test like '%a%' escape '^'");
        $longString = str_repeat("0", (new \ReflectionClassConstant(Database::class, "LIMIT_SET_STRING_LENGTH"))->getValue() + 1);
        return [
            ["test like ? escape '^'",                                    ["%a%"],           ["a"],                              ["test"],         true],
            ["(col1 like ? escape '^' or col2 like ? escape '^')",        ["%a%", "%a%"],    ["a"],                              ["col1", "col2"], true],
            ["(".implode(" or ", $clause).")",                            [],                $terms,                             ["test"],         true],
            ["(".implode(" and ", $clause).")",                           [],                $terms,                             ["test"],         false],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%$longString%"], array_merge($terms, [$longString]), ["test"],         true],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%Eh?%"],         array_merge($terms, ["Eh?"]),       ["test"],         true],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%?%"],           array_merge($terms, ["?"]),         ["test"],         true],
        ];
    }
}
