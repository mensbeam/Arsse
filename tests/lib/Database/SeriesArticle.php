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
                ["john.doe@example.org", "", "John Doe", UserDriver::RIGHTS_NONE],
                ["john.doe@example.net", "", "John Doe", UserDriver::RIGHTS_NONE],
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
                [7,"jane.doe@example.com",9,null],
                [8,"john.doe@example.org",11,null],
                [9,"john.doe@example.org",12,null],
                [10,"john.doe@example.org",13,null],
                [11,"john.doe@example.net",10,null],
                [12,"john.doe@example.net",2,9],
                [13,"john.doe@example.net",3,8],
                [14,"john.doe@example.net",4,7],
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
            'rows' => [ // lower IDs are filled by series setup
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
            'rows' => [ // lower IDs are filled by series setup
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
                'owner'    => "str",
                'article'  => "int",
                'read'     => "bool",
                'starred'  => "bool",
                'modified' => "datetime"
            ],
            'rows' => [
                ["john.doe@example.com",  1,1,1,'2000-01-01 00:00:00'],
                ["john.doe@example.com", 19,1,0,'2000-01-01 00:00:00'],
                ["john.doe@example.com", 20,0,1,'2010-01-01 00:00:00'],
                ["jane.doe@example.com", 20,1,0,'2010-01-01 00:00:00'],
                ["john.doe@example.org",102,1,0,'2000-01-02 02:00:00'],
                ["john.doe@example.org",103,0,1,'2000-01-03 03:00:00'],
                ["john.doe@example.org",104,1,1,'2000-01-04 04:00:00'],
                ["john.doe@example.org",105,0,0,'2000-01-05 05:00:00'],
                ["john.doe@example.net", 19,0,0,'2017-01-01 00:00:00'],
                ["john.doe@example.net", 20,1,0,'2017-01-01 00:00:00'],
                ["john.doe@example.net",  3,0,1,'2017-01-01 00:00:00'],
                ["john.doe@example.net",  4,1,1,'2017-01-01 00:00:00'],
                ["john.doe@example.net", 12,0,1,'2017-01-01 00:00:00'], // user is no longer subscribed to this article's feed; the star should not be counted in articleStarredCount
            ]
        ],
    ];
    protected $matches = [
        [
            'id' => 101,
            'url' => 'http://example.com/1',
            'title' => 'Article title 1',
            'author' => '',
            'content' => '<p>Article content 1</p>',
            'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
            'published_date' => 946684800,
            'edited_date' => 946684801,
            'modified_date' => 946688400,
            'unread' => 1,
            'starred' => 0,
            'edition' => 101,
            'subscription' => 8,
            'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
            'media_url' => null,
            'media_type' => null,
        ],
        [
            'id' => 102,
            'url' => 'http://example.com/2',
            'title' => 'Article title 2',
            'author' => '',
            'content' => '<p>Article content 2</p>',
            'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
            'published_date' => 946771200,
            'edited_date' => 946771202,
            'modified_date' => 946778400,
            'unread' => 0,
            'starred' => 0,
            'edition' => 202,
            'subscription' => 8,
            'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
            'media_url' => "http://example.com/text",
            'media_type' => "text/plain",
        ],
        [
            'id' => 103,
            'url' => 'http://example.com/3',
            'title' => 'Article title 3',
            'author' => '',
            'content' => '<p>Article content 3</p>',
            'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
            'published_date' => 946857600,
            'edited_date' => 946857603,
            'modified_date' => 946868400,
            'unread' => 1,
            'starred' => 1,
            'edition' => 203,
            'subscription' => 9,
            'fingerprint' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
            'media_url' => "http://example.com/video",
            'media_type' => "video/webm",
        ],
        [
            'id' => 104,
            'url' => 'http://example.com/4',
            'title' => 'Article title 4',
            'author' => '',
            'content' => '<p>Article content 4</p>',
            'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
            'published_date' => 946944000,
            'edited_date' => 946944004,
            'modified_date' => 946958400,
            'unread' => 0,
            'starred' => 1,
            'edition' => 204,
            'subscription' => 9,
            'fingerprint' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
            'media_url' => "http://example.com/image",
            'media_type' => "image/svg+xml",
        ],
        [
            'id' => 105,
            'url' => 'http://example.com/5',
            'title' => 'Article title 5',
            'author' => '',
            'content' => '<p>Article content 5</p>',
            'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
            'published_date' => 947030400,
            'edited_date' => 947030405,
            'modified_date' => 947048400,
            'unread' => 1,
            'starred' => 0,
            'edition' => 305,
            'subscription' => 10,
            'fingerprint' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
            'media_url' => "http://example.com/audio",
            'media_type' => "audio/ogg",
        ],
    ];

    function setUpSeries() {
        for($a = 0, $b = 1; $b <= 10; $b++) {
            // add two generic articles per feed, and matching initial editions
            $this->data['arsse_articles']['rows'][] = [++$a,$b,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"];
            $this->data['arsse_editions']['rows'][] = [$a,$a];
            $this->data['arsse_articles']['rows'][] = [++$a,$b,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"];
            $this->data['arsse_editions']['rows'][] = [$a,$a];
        }
        $this->checkTables = ['arsse_marks' => ["owner","article","read","starred","modified"],];
        $this->user = "john.doe@example.net";
    }

    protected function compareIds(array $exp, Context $c) {
        $ids = array_column($ids = Data::$db->articleList($this->user, $c)->getAll(), "id");
        sort($ids);
        sort($exp);
        $this->assertEquals($exp, $ids);
    }

    function testListArticlesCheckingContext() {
        $this->user = "john.doe@example.com";
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
    }

    function testListArticlesOfAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->articleList($this->user, (new Context)->folder(1));
    }

    function testListArticlesOfAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->articleList($this->user, (new Context)->subscription(1));
    }

    function testListArticlesCheckingProperties() {
        $this->user = "john.doe@example.org";
        Data::$db->dateFormatDefault("unix");
        $this->assertResult($this->matches, Data::$db->articleList($this->user));
    }

    function testListArticlesWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->articleList($this->user);
    }

    function testMarkAllArticlesUnread() {
        Data::$db->articleMark($this->user, ['read'=>false]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesRead() {
        Data::$db->articleMark($this->user, ['read'=>true]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,1,0,$now];
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesUnstarred() {
        Data::$db->articleMark($this->user, ['starred'=>false]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][10][3] = 0;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][3] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesStarred() {
        Data::$db->articleMark($this->user, ['starred'=>true]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,0,1,$now];
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesUnreadAndUnstarred() {
        Data::$db->articleMark($this->user, ['read'=>false,'starred'=>false]);
        $now = $this->dateTransform(time(), "sql");
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

    function testMarkAllArticlesReadAndStarred() {
        Data::$db->articleMark($this->user, ['read'=>true,'starred'=>true]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,1,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,1,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,1,1,$now];
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesUnreadAndStarred() {
        Data::$db->articleMark($this->user, ['read'=>false,'starred'=>true]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][2] = 0;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $state['arsse_marks']['rows'][11][2] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,0,1,$now];
        $this->compareExpectations($state);
    }

    function testMarkAllArticlesReadAndUnstarred() {
        Data::$db->articleMark($this->user, ['read'=>true,'starred'=>false]);
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][2] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][10][2] = 1;
        $state['arsse_marks']['rows'][10][3] = 0;
        $state['arsse_marks']['rows'][10][4] = $now;
        $state['arsse_marks']['rows'][11][3] = 0;
        $state['arsse_marks']['rows'][11][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,1,0,$now];
        $this->compareExpectations($state);
    }

    function testMarkATreeFolder() {
        Data::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(7));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [$this->user,5,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,1,0,$now];
        $this->compareExpectations($state);
    }

    function testMarkALeafFolder() {
        Data::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(8));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [$this->user,5,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,0,$now];
        $this->compareExpectations($state);
    }

    function testMarkAMissingFolder() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(42));
    }

    function testMarkASubscription() {
        Data::$db->articleMark($this->user, ['read'=>true], (new Context)->subscription(13));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [$this->user,5,1,0,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,1,0,$now];
        $this->compareExpectations($state);
    }

    function testMarkAMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->articleMark($this->user, ['read'=>true], (new Context)->folder(2112));
    }

    function testMarkAnArticle() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->article(20));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAMissingArticle() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->article(1));
    }

    function testMarkAnEdition() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(1001));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAStaleEditionUnread() {
        Data::$db->articleMark($this->user, ['read'=>false], (new Context)->edition(20)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations($state);
    }

    function testMarkAStaleEditionStarred() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(20));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAStaleEditionUnreadAndStarred() {
        Data::$db->articleMark($this->user, ['read'=>false,'starred'=>true], (new Context)->edition(20)); // only starred is changed
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkAStaleEditionUnreadAndUnstarred() {
        Data::$db->articleMark($this->user, ['read'=>false,'starred'=>false], (new Context)->edition(20)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations($state);
    }

    function testMarkAMissingEdition() {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->edition(2));
    }

    function testMarkByOldestEdition() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->oldestEdition(19));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkByLatestEdition() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->latestEdition(20));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][] = [$this->user,5,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,6,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,8,0,1,$now];
        $this->compareExpectations($state);
    }

    function testMarkByLastModified() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->modifiedSince('2017-01-01T00:00:00Z'));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][8][3] = 1;
        $state['arsse_marks']['rows'][8][4] = $now;
        $state['arsse_marks']['rows'][9][3] = 1;
        $state['arsse_marks']['rows'][9][4] = $now;
        $this->compareExpectations($state);
    }

    function testMarkByNotLastModified() {
        Data::$db->articleMark($this->user, ['starred'=>true], (new Context)->notModifiedSince('2000-01-01T00:00:00Z'));
        $now = $this->dateTransform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_marks']['rows'][] = [$this->user,5,0,1,$now];
        $state['arsse_marks']['rows'][] = [$this->user,7,0,1,$now];
        $this->compareExpectations($state);
    }

    function testMarkArticlesWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->articleMark($this->user, ['read'=>false]);
    }

    function testCountStarredArticles() {
        $this->assertSame(2, Data::$db->articleStarredCount("john.doe@example.com"));
        $this->assertSame(2, Data::$db->articleStarredCount("john.doe@example.org"));
        $this->assertSame(2, Data::$db->articleStarredCount("john.doe@example.net"));
        $this->assertSame(0, Data::$db->articleStarredCount("jane.doe@example.com"));
    }

    function testCountStarredArticlesWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->articleStarredCount($this->user);
    }

    function testFetchLatestEdition() {
        $this->assertSame(1001, Data::$db->editionLatest($this->user));
        $this->assertSame(4, Data::$db->editionLatest($this->user, (new Context)->subscription(12)));
    }

    function testFetchLatestEditionOfMissingSubscription() {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Data::$db->editionLatest($this->user, (new Context)->subscription(1));
    }

    function testFetchLatestEditionWithoutAuthority() {
        Phake::when(Data::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Data::$db->editionLatest($this->user);
    }
}