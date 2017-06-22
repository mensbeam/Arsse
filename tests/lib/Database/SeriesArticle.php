<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\Misc\Context;
use Phake;

trait SeriesArticle {
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
                'rights'   => 'int',
            ],
            'rows' => [
                ["jane.doe@example.com", "", "Jane Doe", UserDriver::RIGHTS_NONE],
                ["john.doe@example.com", "", "John Doe", UserDriver::RIGHTS_NONE],
            ],
        ],
        'arsse_folders' => [
            'columns' => [
                'id'     => "int",
                'owner'  => "str",
                'parent' => "int",
                'name'   => "str",
            ],
            'rows' => [
                [1, "john.doe@example.com", null, "Technology"],
                [2, "john.doe@example.com",    1, "Software"],
                [3, "john.doe@example.com",    1, "Rocketry"],
                [4, "jane.doe@example.com", null, "Politics"],        
                [5, "john.doe@example.com", null, "Politics"],
                [6, "john.doe@example.com",    2, "Politics"],
            ]
        ],
        'arsse_feeds' => [
            'columns' => [
                'id'         => "int",
                'url'        => "str",
            ],
            'rows' => [
                [1,"http://example.com/1"],
                [2,"http://example.com/2"],
                [3,"http://example.com/3"],
                [4,"http://example.com/4"],
                [5,"http://example.com/5"],
                [6,"http://example.com/6"],
                [7,"http://example.com/7"],
                [8,"http://example.com/8"],
                [9,"http://example.com/9"],
                [10,"http://example.com/10"],
                [11,"http://example.com/11"],
                [12,"http://example.com/12"],
                [13,"http://example.com/13"],
                [14,"http://example.com/14"],
                [15,"http://example.com/15"],
                [16,"http://example.com/16"],
                [17,"http://example.com/17"],
                [18,"http://example.com/18"],
                [19,"http://example.com/19"],
                [20,"http://example.com/20"],
            ]
        ],
        'arsse_subscriptions' => [
            'columns' => [
                'id'         => "int",
                'owner'      => "str",
                'feed'       => "int",
                'folder'     => "int",
            ],
            'rows' => [
                [1,"john.doe@example.com",1,null],
                [2,"john.doe@example.com",2,null],
                [3,"john.doe@example.com",3,1],
                [4,"john.doe@example.com",4,6],
                [5,"john.doe@example.com",10,5],
                [6,"jane.doe@example.com",1,null],
                [7,"jane.doe@example.com",12,null],/*
                [8,"john.doe@example.com",1,null],
                [9,"john.doe@example.com",1,null],
                [10,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],
                [1,"john.doe@example.com",1,null],*/
            ]
        ],
        'arsse_articles' => [
            'columns' => [
                'id'                 => "int",
                'feed'               => "int",
                'modified'           => "datetime",
                'url_title_hash'     => "str",
                'url_content_hash'   => "str",
                'title_content_hash' => "str",
            ],
            'rows' => [] // filled by series setup
        ],
        'arsse_enclosures' => [
            'columns' => [
                'article' => "int",
                'url'     => "str",
                'type'    => "str",
            ],
            'rows' => []
        ],
        'arsse_editions' => [
            'columns' => [
                'id'       => "int",
                'article'  => "int",
            ],
            'rows' => [ // lower IDs are filled by series setup
                [1001,20],
            ]
        ],
        'arsse_marks' => [
            'columns' => [
                'owner'   => "str",
                'article' => "int",
                'read'    => "bool",
                'starred' => "bool",
            ],
            'rows' => [
                ["john.doe@example.com",1,1,1],
                ["john.doe@example.com",19,1,0],
                ["john.doe@example.com",20,0,1],
                ["jane.doe@example.com",20,1,0],
            ]
        ],
    ];

    function setUpSeries() {
        for($a = 0, $b = 1; $b <= sizeof($this->data['arsse_feeds']['rows']); $b++) {
            // add two generic articles per feed, and matching initial editions
            $this->data['arsse_articles']['rows'][] = [++$a,$b,"2000-01-01T00:00:00Z","","",""];
            $this->data['arsse_editions']['rows'][] = [$a,$a];
            $this->data['arsse_articles']['rows'][] = [++$a,$b,"2010-01-01T00:00:00Z","","",""];
            $this->data['arsse_editions']['rows'][] = [$a,$a];
        }
        $this->user = "john.doe@example.com";
    }

    function testListArticlesByContext() {
        // get all items for user
        $exp = [1,2,3,4,5,6,7,8,19,20];
        $this->compareIds($exp, new Context);
        // get items from a folder tree
        $exp = [5,6,7,8];
        $this->compareIds($exp, (new Context)->folder(1));
        // get items from a leaf folder
        $exp = [7,8];
        $this->compareIds($exp, (new Context)->folder(6));
        // get items from a single subscription
        $exp = [19,20];
        $this->compareIds($exp, (new Context)->subscription(5));
        // get un/read items from a single subscription
        $this->compareIds([20], (new Context)->subscription(5)->unread(true));
        $this->compareIds([19], (new Context)->subscription(5)->unread(false));
        // get starred articles
        $this->compareIds([1,20], (new Context)->starred(true));
        $this->compareIds([1], (new Context)->starred(true)->unread(false));
        $this->compareIds([], (new Context)->starred(true)->unread(false)->subscription(5));
        // get items relative to edition
        $this->compareIds([19], (new Context)->subscription(5)->latestEdition(999));
        $this->compareIds([19], (new Context)->subscription(5)->latestEdition(19));
        $this->compareIds([20], (new Context)->subscription(5)->oldestEdition(999));
        $this->compareIds([20], (new Context)->subscription(5)->oldestEdition(1001));
        // get items relative to modification date
        $exp = [2,4,6,8,20];
        $this->compareIds($exp, (new Context)->modifiedSince("2005-01-01T00:00:00Z"));
        $this->compareIds($exp, (new Context)->modifiedSince("2010-01-01T00:00:00Z"));
        $exp = [1,3,5,7,19];
        $this->compareIds($exp, (new Context)->notModifiedSince("2005-01-01T00:00:00Z"));
        $this->compareIds($exp, (new Context)->notModifiedSince("2000-01-01T00:00:00Z"));

    }

    protected function compareIds(array $exp, Context $c) {
        $ids = array_column($ids = Data::$db->articleList($this->user, $c)->getAll(), "id");
        sort($ids);
        sort($exp);
        $this->assertEquals($exp, $ids);
    }
}