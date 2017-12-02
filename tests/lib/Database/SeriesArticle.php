<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;

use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\Date;
use Phake;

trait SeriesArticle {
    protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
            ],
            'rows' => [
                ["jane.doe@example.com", "", "Jane Doe"],
                ["john.doe@example.com", "", "John Doe"],
                ["john.doe@example.org", "", "John Doe"],
                ["john.doe@example.net", "", "John Doe"],
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
                [7, "john.doe@example.net", null, "Technology"],
                [8, "john.doe@example.net",    7, "Software"],
                [9, "john.doe@example.net", null, "Politics"],
            ]
        ],
        'arsse_feeds' => [
            'columns' => [
                'id'         => "int",
                'url'        => "str",
                'title'      => "str",
            ],
            'rows' => [
                [1,"http://example.com/1", "Feed 1"],
                [2,"http://example.com/2", "Feed 2"],
                [3,"http://example.com/3", "Feed 3"],
                [4,"http://example.com/4", "Feed 4"],
                [5,"http://example.com/5", "Feed 5"],
                [6,"http://example.com/6", "Feed 6"],
                [7,"http://example.com/7", "Feed 7"],
                [8,"http://example.com/8", "Feed 8"],
                [9,"http://example.com/9", "Feed 9"],
                [10,"http://example.com/10", "Feed 10"],
                [11,"http://example.com/11", "Feed 11"],
                [12,"http://example.com/12", "Feed 12"],
                [13,"http://example.com/13", "Feed 13"],
            ]
        ],
        'arsse_subscriptions' => [
            'columns' => [
                'id'         => "int",
                'owner'      => "str",
                'feed'       => "int",
                'folder'     => "int",
                'title'      => "str",
            ],
            'rows' => [
                [1, "john.doe@example.com",1, null,"Subscription 1"],
                [2, "john.doe@example.com",2, null,null],
                [3, "john.doe@example.com",3,    1,"Subscription 3"],
                [4, "john.doe@example.com",4,    6,null],
                [5, "john.doe@example.com",10,   5,"Subscription 5"],
                [6, "jane.doe@example.com",1, null,null],
                [7, "jane.doe@example.com",10,null,"Subscription 7"],
                [8, "john.doe@example.org",11,null,null],
                [9, "john.doe@example.org",12,null,"Subscription 9"],
                [10,"john.doe@example.org",13,null,null],
                [11,"john.doe@example.net",10,null,"Subscription 11"],
                [12,"john.doe@example.net",2,    9,null],
                [13,"john.doe@example.net",3,    8,"Subscription 13"],
                [14,"john.doe@example.net",4,    7,null],
            ]
        ],
        'arsse_articles' => [
            'columns' => [
                'id'                 => "int",
                'feed'               => "int",
                'url'                => "str",
                'title'              => "str",
                'author'             => "str",
                'published'          => "datetime",
                'edited'             => "datetime",
                'content'            => "str",
                'guid'               => "str",
                'url_title_hash'     => "str",
                'url_content_hash'   => "str",
                'title_content_hash' => "str",
                'modified'           => "datetime",
            ],
            'rows' => [
                [1,1,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [2,1,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [3,2,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [4,2,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [5,3,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [6,3,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [7,4,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [8,4,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [9,5,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [10,5,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [11,6,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [12,6,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [13,7,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [14,7,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [15,8,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [16,8,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [17,9,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [18,9,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [19,10,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                [20,10,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                [101,11,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:01','<p>Article content 1</p>','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207','2000-01-01 01:00:00'],
                [102,11,'http://example.com/2','Article title 2','','2000-01-02 00:00:00','2000-01-02 00:00:02','<p>Article content 2</p>','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e','2000-01-02 02:00:00'],
                [103,12,'http://example.com/3','Article title 3','','2000-01-03 00:00:00','2000-01-03 00:00:03','<p>Article content 3</p>','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b','2000-01-03 03:00:00'],
                [104,12,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:04','<p>Article content 4</p>','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9','2000-01-04 04:00:00'],
                [105,13,'http://example.com/5','Article title 5','','2000-01-05 00:00:00','2000-01-05 00:00:05','<p>Article content 5</p>','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba','2000-01-05 05:00:00'],
            ]
        ],
        'arsse_enclosures' => [
            'columns' => [
                'article' => "int",
                'url'     => "str",
                'type'    => "str",
            ],
            'rows' => [
                [102,"http://example.com/text","text/plain"],
                [103,"http://example.com/video","video/webm"],
                [104,"http://example.com/image","image/svg+xml"],
                [105,"http://example.com/audio","audio/ogg"],

            ]
        ],
        'arsse_editions' => [
            'columns' => [
                'id'       => "int",
                'article'  => "int",
            ],
            'rows' => [
                [1,1],
                [2,2],
                [3,3],
                [4,4],
                [5,5],
                [6,6],
                [7,7],
                [8,8],
                [9,9],
                [10,10],
                [11,11],
                [12,12],
                [13,13],
                [14,14],
                [15,15],
                [16,16],
                [17,17],
                [18,18],
                [19,19],
                [20,20],
                [101,101],
                [102,102],
                [103,103],
                [104,104],
                [105,105],
                [202,102],
                [203,103],
                [204,104],
                [205,105],
                [305,105],
                [1001,20],
            ]
        ],
        'arsse_marks' => [
            'columns' => [
                'subscription' => "int",
                'article'      => "int",
                'read'         => "bool",
                'starred'      => "bool",
                'modified'     => "datetime",
                'note'         => "str",
            ],
            'rows' => [
                [1,   1,1,1,'2000-01-01 00:00:00',''],
                [5,  19,1,0,'2016-01-01 00:00:00',''],
                [5,  20,0,1,'2005-01-01 00:00:00',''],
                [7,  20,1,0,'2010-01-01 00:00:00',''],
                [8, 102,1,0,'2000-01-02 02:00:00','Note 2'],
                [9, 103,0,1,'2000-01-03 03:00:00','Note 3'],
                [9, 104,1,1,'2000-01-04 04:00:00','Note 4'],
                [10,105,0,0,'2000-01-05 05:00:00',''],
                [11, 19,0,0,'2017-01-01 00:00:00','ook'],
                [11, 20,1,0,'2017-01-01 00:00:00','eek'],
                [12,  3,0,1,'2017-01-01 00:00:00','ack'],
                [12,  4,1,1,'2017-01-01 00:00:00','ach'],
                [1,   2,0,0,'2010-01-01 00:00:00','Some Note'],
            ]
        ],
        'arsse_categories' => [ // author-supplied categories
            'columns' => [
                'article'  => "int",
                'name'     => "str",
            ],
            'rows' => [
                [19,"Fascinating"],
                [19,"Logical"],
                [20,"Interesting"],
                [20,"Logical"],
            ],
        ],
        'arsse_labels' => [
            'columns' => [
                'id'       => "int",
                'owner'    => "str",
                'name'     => "str",
            ],
            'rows' => [
                [1,"john.doe@example.com","Interesting"],
                [2,"john.doe@example.com","Fascinating"],
                [3,"jane.doe@example.com","Boring"],
                [4,"john.doe@example.com","Lonely"],
            ],
        ],
        'arsse_label_members' => [
            'columns' => [
                'label'        => "int",
                'article'      => "int",
                'subscription' => "int",
                'assigned'     => "bool",
                'modified'     => "datetime",
            ],
            'rows' => [
                [1, 1,1,1,'2000-01-01 00:00:00'],
                [2, 1,1,1,'2000-01-01 00:00:00'],
                [1,19,5,1,'2000-01-01 00:00:00'],
                [2,20,5,1,'2000-01-01 00:00:00'],
                [1, 5,3,0,'2000-01-01 00:00:00'],
                [2, 5,3,1,'2000-01-01 00:00:00'],
                [4, 7,4,0,'2000-01-01 00:00:00'],
                [4, 8,4,1,'2015-01-01 00:00:00'],
            ],
        ],
    ];
    protected $matches = [
        [
            'id' => 101,
            'url' => 'http://example.com/1',
            'title' => 'Article title 1',
            'subscription_title' => "Feed 11",
            'author' => '',
            'content' => '<p>Article content 1</p>',
            'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
            'published_date' => '2000-01-01 00:00:00',
            'edited_date' => '2000-01-01 00:00:01',
            'modified_date' => '2000-01-01 01:00:00',
            'unread' => 1,
            'starred' => 0,
            'edition' => 101,
            'subscription' => 8,
            'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
            'media_url' => null,
            'media_type' => null,
            'note' => "",
        ],
        [
            'id' => 102,
            'url' => 'http://example.com/2',
            'title' => 'Article title 2',
            'subscription_title' => "Feed 11",
            'author' => '',
            'content' => '<p>Article content 2</p>',
            'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
            'published_date' => '2000-01-02 00:00:00',
            'edited_date' => '2000-01-02 00:00:02',
            'modified_date' => '2000-01-02 02:00:00',
            'unread' => 0,
            'starred' => 0,
            'edition' => 202,
            'subscription' => 8,
            'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
            'media_url' => "http://example.com/text",
            'media_type' => "text/plain",
            'note' => "Note 2",
        ],
        [
            'id' => 103,
            'url' => 'http://example.com/3',
            'title' => 'Article title 3',
            'subscription_title' => "Subscription 9",
            'author' => '',
            'content' => '<p>Article content 3</p>',
            'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
            'published_date' => '2000-01-03 00:00:00',
            'edited_date' => '2000-01-03 00:00:03',
            'modified_date' => '2000-01-03 03:00:00',
            'unread' => 1,
            'starred' => 1,
            'edition' => 203,
            'subscription' => 9,
            'fingerprint' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
            'media_url' => "http://example.com/video",
            'media_type' => "video/webm",
            'note' => "Note 3",
        ],
        [
            'id' => 104,
            'url' => 'http://example.com/4',
            'title' => 'Article title 4',
            'subscription_title' => "Subscription 9",
            'author' => '',
            'content' => '<p>Article content 4</p>',
            'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
            'published_date' => '2000-01-04 00:00:00',
            'edited_date' => '2000-01-04 00:00:04',
            'modified_date' => '2000-01-04 04:00:00',
            'unread' => 0,
            'starred' => 1,
            'edition' => 204,
            'subscription' => 9,
            'fingerprint' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
            'media_url' => "http://example.com/image",
            'media_type' => "image/svg+xml",
            'note' => "Note 4",
        ],
        [
            'id' => 105,
            'url' => 'http://example.com/5',
            'title' => 'Article title 5',
            'subscription_title' => "Feed 13",
            'author' => '',
            'content' => '<p>Article content 5</p>',
            'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
            'published_date' => '2000-01-05 00:00:00',
            'edited_date' => '2000-01-05 00:00:05',
            'modified_date' => '2000-01-05 05:00:00',
            'unread' => 1,
            'starred' => 0,
            'edition' => 305,
            'subscription' => 10,
            'fingerprint' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
            'media_url' => "http://example.com/audio",
            'media_type' => "audio/ogg",
            'note' => "",
        ],
    ];
    protected $fields = [
        Database::LIST_MINIMAL => [
            "id", "subscription", "feed", "modified_date", "marked_date", "unread", "starred", "edition", "edited_date",
        ],
        Database::LIST_CONSERVATIVE => [
            "id", "subscription", "feed", "modified_date", "marked_date", "unread", "starred", "edition", "edited_date",
            "url", "title", "subscription_title", "author", "guid", "published_date", "fingerprint",
        ],
        Database::LIST_TYPICAL => [
            "id", "subscription", "feed", "modified_date", "marked_date", "unread", "starred", "edition", "edited_date",
            "url", "title", "subscription_title", "author", "guid", "published_date", "fingerprint",
            "content", "media_url", "media_type",
        ],
        Database::LIST_FULL => [
            "id", "subscription", "feed", "modified_date", "marked_date", "unread", "starred", "edition", "edited_date",
            "url", "title", "subscription_title", "author", "guid", "published_date", "fingerprint",
            "content", "media_url", "media_type",
            "note",
        ],
    ];

    public function setUpSeries() {
        $this->checkTables = ['arsse_marks' => ["subscription","article","read","starred","modified","note"],];
        $this->user = "john.doe@example.net";
    }

    protected function compareIds(array $exp, Context $c) {
        $ids = array_column($ids = Arsse::$db->articleList($this->user, $c)->getAll(), "id");
        sort($ids);
        sort($exp);
        $this->assertEquals($exp, $ids);
    }

    public function testListArticlesCheckingContext() {
        $this->user = "john.doe@example.com";
        // get all items for user
        $exp = [1,2,3,4,5,6,7,8,19,20];
        $this->compareIds($exp, new Context);
        $this->compareIds($exp, (new Context)->articles(range(1, Database::LIMIT_ARTICLES * 3)));
        // get items from a folder tree
        $this->compareIds([5,6,7,8], (new Context)->folder(1));
        // get items from a leaf folder
        $this->compareIds([7,8], (new Context)->folder(6));
        // get items from a non-leaf folder without descending
        $this->compareIds([1,2,3,4], (new Context)->folderShallow(0));
        $this->compareIds([5,6], (new Context)->folderShallow(1));
        // get items from a single subscription
        $exp = [19,20];
        $this->compareIds($exp, (new Context)->subscription(5));
        // get un/read items from a single subscription
        $this->compareIds([20], (new Context)->subscription(5)->unread(true));
        $this->compareIds([19], (new Context)->subscription(5)->unread(false));
        // get starred articles
        $this->compareIds([1,20], (new Context)->starred(true));
        $this->compareIds([2,3,4,5,6,7,8,19], (new Context)->starred(false));
        $this->compareIds([1], (new Context)->starred(true)->unread(false));
        $this->compareIds([], (new Context)->starred(true)->unread(false)->subscription(5));
        // get items relative to edition
        $this->compareIds([19], (new Context)->subscription(5)->latestEdition(999));
        $this->compareIds([19], (new Context)->subscription(5)->latestEdition(19));
        $this->compareIds([20], (new Context)->subscription(5)->oldestEdition(999));
        $this->compareIds([20], (new Context)->subscription(5)->oldestEdition(1001));
        // get items relative to article ID
        $this->compareIds([1,2,3], (new Context)->latestArticle(3));
        $this->compareIds([19,20], (new Context)->oldestArticle(19));
        // get items relative to (feed) modification date
        $exp = [2,4,6,8,20];
        $this->compareIds($exp, (new Context)->modifiedSince("2005-01-01T00:00:00Z"));
        $this->compareIds($exp, (new Context)->modifiedSince("2010-01-01T00:00:00Z"));
        $exp = [1,3,5,7,19];
        $this->compareIds($exp, (new Context)->notModifiedSince("2005-01-01T00:00:00Z"));
        $this->compareIds($exp, (new Context)->notModifiedSince("2000-01-01T00:00:00Z"));
        // get items relative to (user) modification date (both marks and labels apply)
        $this->compareIds([8,19], (new Context)->markedSince("2014-01-01T00:00:00Z"));
        $this->compareIds([2,4,6,8,19,20], (new Context)->markedSince("2010-01-01T00:00:00Z"));
        $this->compareIds([1,2,3,4,5,6,7,20], (new Context)->notMarkedSince("2014-01-01T00:00:00Z"));
        $this->compareIds([1,3,5,7], (new Context)->notMarkedSince("2005-01-01T00:00:00Z"));
        // paged results
        $this->compareIds([1], (new Context)->limit(1));
        $this->compareIds([2], (new Context)->limit(1)->oldestEdition(1+1));
        $this->compareIds([3], (new Context)->limit(1)->oldestEdition(2+1));
        $this->compareIds([4,5], (new Context)->limit(2)->oldestEdition(3+1));
        // reversed results
        $this->compareIds([20], (new Context)->reverse(true)->limit(1));
        $this->compareIds([19], (new Context)->reverse(true)->limit(1)->latestEdition(1001-1));
        $this->compareIds([8], (new Context)->reverse(true)->limit(1)->latestEdition(19-1));
        $this->compareIds([7,6], (new Context)->reverse(true)->limit(2)->latestEdition(8-1));
        // get articles by label ID
        $this->compareIds([1,19], (new Context)->label(1));
        $this->compareIds([1,5,20], (new Context)->label(2));
        // get articles by label name
        $this->compareIds([1,19], (new Context)->labelName("Interesting"));
        $this->compareIds([1,5,20], (new Context)->labelName("Fascinating"));
        // get articles with any or no label
        $this->compareIds([1,5,8,19,20], (new Context)->labelled(true));
        $this->compareIds([2,3,4,6,7], (new Context)->labelled(false));
        // get a specific article or edition
        $this->compareIds([20], (new Context)->article(20));
        $this->compareIds([20], (new Context)->edition(1001));
        // get multiple specific articles or editions
        $this->compareIds([1,20], (new Context)->articles([1,20,50]));
        $this->compareIds([1,20], (new Context)->editions([1,1001,50]));
        // get articles base on whether or not they have notes
        $this->compareIds([1,3,4,5,6,7,8,19,20], (new Context)->annotated(false));
        $this->compareIds([2], (new Context)->annotated(true));
        // get specific starred articles
        $this->compareIds([1], (new Context)->articles([1,2,3])->starred(true));
        $this->compareIds([2,3], (new Context)->articles([1,2,3])->starred(false));
    }

    public function testListArticlesOfAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->folder(1));
    }

    public function testListArticlesOfAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->subscription(1));
    }

    public function testListArticlesCheckingProperties() {
        $this->user = "john.doe@example.org";
        $this->assertResult($this->matches, Arsse::$db->articleList($this->user));
        // check that the different fieldset groups return the expected columns
        foreach ($this->fields as $constant => $columns) {
            $test = array_keys(Arsse::$db->articleList($this->user, (new Context)->article(101), $constant)->getRow());
            sort($columns);
            sort($test);
            $this->assertEquals($columns, $test, "Fields do not match expectation for verbosity $constant");
        }
        // check that an unknown fieldset produces an exception
        $this->assertException("constantUnknown");
        Arsse::$db->articleList($this->user, (new Context)->article(101), \PHP_INT_MAX);
    }

    public function testListArticlesWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleList($this->user);
    }

    public function testMarkAllArticlesUnread() {
        Arsse::$db->articleMark($this->user, ['read'=>false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesRead() {
        Arsse::$db->articleMark($this->user, ['read'=>true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,1,0,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesUnstarred() {
        Arsse::$db->articleMark($this->user, ['starred'=>false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][10][3] = 0;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][3] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesStarred() {
        Arsse::$db->articleMark($this->user, ['starred'=>true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesUnreadAndUnstarred() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][10][3] = 0;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][3] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesReadAndStarred() {
        Arsse::$db->articleMark($this->user, ['read'=>true,'starred'=>true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,1,1,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,1,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,1,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesUnreadAndStarred() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAllArticlesReadAndUnstarred() {
        Arsse::$db->articleMark($this->user, ['read'=>true,'starred'=>false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][3] = 0;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][3] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,1,0,$now,''];
        $this->compareExpectations($state);
    }

    public function testSetNoteForAllArticles() {
        Arsse::$db->articleMark($this->user, ['note'=>"New note"]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][5] = "New note";
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][5] = "New note";
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][10][5] = "New note";
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][5] = "New note";
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,0,0,$now,'New note'];
        $state['arsse_marks']['rows'][] = [13,6,0,0,$now,'New note'];
        $state['arsse_marks']['rows'][] = [14,7,0,0,$now,'New note'];
        $state['arsse_marks']['rows'][] = [14,8,0,0,$now,'New note'];
        $this->compareExpectations($state);
    }

    public function testMarkATreeFolder() {
        Arsse::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(7));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [13,5,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,1,0,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkALeafFolder() {
        Arsse::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(8));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [13,5,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,0,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(42));
    }

    public function testMarkASubscription() {
        Arsse::$db->articleMark($this->user, ['read'=>true], (new Context)->subscription(13));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [13,5,1,0,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,1,0,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(2112));
    }

    public function testMarkAnArticle() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->article(20));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkMultipleArticles() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->articles([2,4,7,20]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkMultipleArticlessUnreadAndStarred() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->articles([2,4,7,20]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkTooFewMultipleArticles() {
        $this->assertException("tooShort", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->articles([]));
    }

    public function testMarkTooManyMultipleArticles() {
        $this->assertSame(7, Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->articles(range(1, Database::LIMIT_ARTICLES * 3))));
    }

    public function testMarkAMissingArticle() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->article(1));
    }

    public function testMarkAnEdition() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(1001));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkMultipleEditions() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->editions([2,4,7,20]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkMultipleEditionsUnread() {
        Arsse::$db->articleMark($this->user, ['read'=>false], (new Context)->editions([2,4,7,1001]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkMultipleEditionsUnreadWithStale() {
        Arsse::$db->articleMark($this->user, ['read'=>false], (new Context)->editions([2,4,7,20]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkMultipleEditionsUnreadAndStarredWithStale() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->editions([2,4,7,20]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkTooFewMultipleEditions() {
        $this->assertException("tooShort", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->editions([]));
    }

    public function testMarkTooManyMultipleEditions() {
        $this->assertSame(7, Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->editions(range(1, 51))));
    }

    public function testMarkAStaleEditionUnread() {
        Arsse::$db->articleMark($this->user, ['read'=>false], (new Context)->edition(20)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations($state);
    }

    public function testMarkAStaleEditionStarred() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(20));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkAStaleEditionUnreadAndStarred() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->edition(20)); // only starred is changed
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkAStaleEditionUnreadAndUnstarred() {
        Arsse::$db->articleMark($this->user, ['read'=>false,'starred'=>false], (new Context)->edition(20)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations($state);
    }

    public function testMarkAMissingEdition() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(2));
    }

    public function testMarkByOldestEdition() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->oldestEdition(19));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkByLatestEdition() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->latestEdition(20));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][] = [13,5,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [13,6,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,8,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkByLastMarked() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->markedSince('2017-01-01T00:00:00Z'));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    public function testMarkByNotLastMarked() {
        Arsse::$db->articleMark($this->user, ['starred'=>true], (new Context)->notMarkedSince('2000-01-01T00:00:00Z'));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [13,5,0,1,$now,''];
        $state['arsse_marks']['rows'][] = [14,7,0,1,$now,''];
        $this->compareExpectations($state);
    }

    public function testMarkArticlesWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleMark($this->user, ['read'=>false]);
    }

    public function testCountArticles() {
        $this->assertSame(2, Arsse::$db->articleCount("john.doe@example.com", (new Context)->starred(true)));
        $this->assertSame(4, Arsse::$db->articleCount("john.doe@example.com", (new Context)->folder(1)));
        $this->assertSame(0, Arsse::$db->articleCount("jane.doe@example.com", (new Context)->starred(true)));
        $this->assertSame(10, Arsse::$db->articleCount("john.doe@example.com", (new Context)->articles(range(1, Database::LIMIT_ARTICLES *3))));
    }

    public function testCountArticlesWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleCount($this->user);
    }

    public function testFetchStarredCounts() {
        $exp1 = ['total' => 2, 'unread' => 1, 'read' => 1];
        $exp2 = ['total' => 0, 'unread' => 0, 'read' => 0];
        $this->assertSame($exp1, Arsse::$db->articleStarred("john.doe@example.com"));
        $this->assertSame($exp2, Arsse::$db->articleStarred("jane.doe@example.com"));
    }

    public function testFetchStarredCountsWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleStarred($this->user);
    }

    public function testFetchLatestEdition() {
        $this->assertSame(1001, Arsse::$db->editionLatest($this->user));
        $this->assertSame(4, Arsse::$db->editionLatest($this->user, (new Context)->subscription(12)));
    }

    public function testFetchLatestEditionOfMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->editionLatest($this->user, (new Context)->subscription(1));
    }

    public function testFetchLatestEditionWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->editionLatest($this->user);
    }

    public function testListTheLabelsOfAnArticle() {
        $this->assertEquals([2,1], Arsse::$db->articleLabelsGet("john.doe@example.com", 1));
        $this->assertEquals([2], Arsse::$db->articleLabelsGet("john.doe@example.com", 5));
        $this->assertEquals([], Arsse::$db->articleLabelsGet("john.doe@example.com", 2));
        $this->assertEquals(["Fascinating","Interesting"], Arsse::$db->articleLabelsGet("john.doe@example.com", 1, true));
        $this->assertEquals(["Fascinating"], Arsse::$db->articleLabelsGet("john.doe@example.com", 5, true));
        $this->assertEquals([], Arsse::$db->articleLabelsGet("john.doe@example.com", 2, true));
    }

    public function testListTheLabelsOfAMissingArticle() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleLabelsGet($this->user, 101);
    }

    public function testListTheLabelsOfAnArticleWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleLabelsGet("john.doe@example.com", 1);
    }

    public function testListTheCategoriesOfAnArticle() {
        $exp = ["Fascinating", "Logical"];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 19));
        $exp = ["Interesting", "Logical"];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 20));
        $exp = [];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 4));
    }

    public function testListTheCategoriesOfAMissingArticle() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleCategoriesGet($this->user, 101);
    }

    public function testListTheCategoriesOfAnArticleWithoutAuthority() {
        Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->articleCategoriesGet($this->user, 19);
    }
}
