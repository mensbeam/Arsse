<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\Context\RootContext;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversMethod;

trait SeriesArticle {
    protected static $drv;
    protected $fields;

    protected function setUpSeriesArticle(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "", 1],
                    ["john.doe@example.com", "", 2],
                    ["john.doe@example.org", "", 3],
                    ["john.doe@example.net", "", 4],
                    ["jill.doe@example.com", "", 5],
                ],
            ],
            'arsse_folders' => [
                'columns' => ["id", "owner", "parent", "name"],
                'rows'    => [
                    [1, "john.doe@example.com", null, "Technology"],
                    [2, "john.doe@example.com",    1, "Software"],
                    [3, "john.doe@example.com",    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, "Politics"],
                    [5, "john.doe@example.com", null, "Politics"],
                    [6, "john.doe@example.com",    2, "Politics"],
                    [7, "john.doe@example.net", null, "Technology"],
                    [8, "john.doe@example.net",    7, "Software"],
                    [9, "john.doe@example.net", null, "Politics"],
                ],
            ],
            'arsse_tags' => [
                'columns' => ["id", "owner", "name"],
                'rows'    => [
                    [1, "john.doe@example.com", "Technology"],
                    [2, "john.doe@example.com", "Software"],
                    [3, "john.doe@example.com", "Rocketry"],
                    [4, "jane.doe@example.com", "Politics"],
                    [5, "john.doe@example.com", "Politics"],
                    [6, "john.doe@example.net", "Technology"],
                    [7, "john.doe@example.net", "Software"],
                    [8, "john.doe@example.net", "Politics"],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "feed_title", "folder", "title", "scrape", "deleted"],
                'rows'    => [
                    [1, "john.doe@example.com","http://example.com/1",  "Feed 1", null,"Subscription 1", 0,0],
                    [2, "john.doe@example.com","http://example.com/2",  "Feed 2", null,null,             0,0],
                    [3, "john.doe@example.com","http://example.com/3",  "Feed 3",    1,"Subscription 3", 0,0],
                    [4, "john.doe@example.com","http://example.com/4",  "Feed 4",    6,null,             0,0],
                    [5, "john.doe@example.com","http://example.com/10", "Feed 10",   5,"Subscription 5", 0,0],
                    [6, "jane.doe@example.com","http://example.com/1",  "Feed 1", null,null,             0,0],
                    [7, "jane.doe@example.com","http://example.com/10", "Feed 10",null,"Subscription 7", 0,0],
                    [8, "john.doe@example.org","http://example.com/11", "Feed 11",null,null,             0,0],
                    [9, "john.doe@example.org","http://example.com/12", "Feed 12",null,"Subscription 9", 0,0],
                    [10,"john.doe@example.org","http://example.com/13", "Feed 13",null,null,             0,0],
                    [11,"john.doe@example.net","http://example.com/10", "Feed 10",null,"Subscription 11",0,0],
                    [12,"john.doe@example.net","http://example.com/2",  "Feed 2",    9,null,             0,0],
                    [13,"john.doe@example.net","http://example.com/3",  "Feed 3",    8,"Subscription 13",0,0],
                    [14,"john.doe@example.net","http://example.com/4",  "Feed 4",    7,null,             0,0],
                    [15,"jill.doe@example.com","http://example.com/11", "Feed 11",null,null,             1,0],
                    [16,"john.doe@example.com","http://example.com/16", "Feed 16",   1,"Subscription 16",0,1],
                ],
            ],
            'arsse_tag_members' => [
                'columns' => ["tag", "subscription", "assigned"],
                'rows'    => [
                    [1,3,1],
                    [1,4,1],
                    [2,4,1],
                    [5,1,0],
                    [5,4,1],
                    [5,5,1],
                    [6,13,1],
                    [6,14,1],
                    [7,13,1],
                    [8,12,1],
                ],
            ],
            'arsse_articles' => [
                'columns' => [
                    "id", "subscription", "url", "title", "author", "published", "edited", "guid",
                    "url_title_hash", "url_content_hash", "title_content_hash", "added", "modified",
                    "read", "starred", "hidden", "marked", "note",
                ],
                'rows' => [
                    [1,   1,null,                  "Title one",      null,      "1800-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",1,1,0,'2000-01-01 00:00:00',''],
                    [2,   1,null,                  "Title two",      null,      "1810-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,0,'2010-01-01 00:00:00','Some Note'],
                    [3,   2,null,                  "Title three",    null,      "1800-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [4,   2,null,                  null,             "John Doe","1810-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [5,   3,null,                  null,             "John Doe","1800-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [6,   3,null,                  null,             "Jane Doe","1810-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,1,'2000-01-01 00:00:00',''],
                    [7,   4,null,                  null,             "Jane Doe","1800-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [8,   4,null,                  null,             null,      "1810-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [19,  5,null,                  null,             null,      "1800-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",1,0,0,'2016-01-01 00:00:00',''],
                    [20,  5,null,                  null,             null,      "1810-01-01 00:00:00",null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,1,0,'2005-01-01 00:00:00',''],
                    [501, 6,null,                  "Title one",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,1,1,'2000-01-01 00:00:00',''],
                    [502, 6,null,                  "Title two",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",1,0,1,'2010-01-01 00:00:00',''],
                    [519, 7,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [520, 7,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",1,0,0,'2010-01-01 00:00:00',''],
                    [101, 8,'http://example.com/1','Article title 1','',        '2000-01-01 00:00:00','2000-01-01 00:00:01','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207','1900-01-01 01:00:00','2000-01-01 01:00:00',0,0,0,null,                 ''],
                    [102, 8,'http://example.com/2','Article title 2','',        '2000-01-02 00:00:00','2000-01-02 00:00:02','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e','1900-01-02 02:00:00','2000-01-02 02:00:00',1,0,0,'2000-01-02 02:00:00','Note 2'],
                    [103, 9,'http://example.com/3','Article title 3','',        '2000-01-03 00:00:00','2000-01-03 00:00:03','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b','1900-01-03 03:00:00','2000-01-03 03:00:00',0,1,0,'2000-01-03 03:00:00','Note 3'],
                    [104, 9,'http://example.com/4','Article title 4','',        '2000-01-04 00:00:00','2000-01-04 00:00:04','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9','1900-01-04 04:00:00','2000-01-04 04:00:00',1,1,0,'2000-01-04 04:00:00','Note 4'],
                    [105,10,'http://example.com/5','Article title 5','',        '2000-01-05 00:00:00','2000-01-05 00:00:05','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba','1900-01-05 05:00:00','2000-01-05 05:00:00',0,0,0,'2000-01-05 05:00:00',''],
                    [119,11,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,'2017-01-01 00:00:00','ook'],
                    [120,11,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",1,0,0,'2017-01-01 00:00:00','eek'],
                    [203,12,null,                  "Title three",    null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,1,0,'2017-01-01 00:00:00','ack'],
                    [204,12,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",1,1,0,'2017-01-01 00:00:00','ach'],
                    [205,13,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [206,13,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [207,14,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [208,14,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1910-01-01 00:00:00","2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [801,15,'http://example.com/1','Article title 1','',        '2000-01-01 00:00:00','2000-01-01 00:00:01','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207','1900-01-01 01:00:00','2000-01-01 01:00:00',0,0,0,null,                 ''],
                    [802,15,'http://example.com/2','Article title 2','',        '2000-01-02 00:00:00','2000-01-02 00:00:02','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e','1900-01-02 02:00:00','2000-01-02 02:00:00',0,0,0,null,                 ''],
                    [999,16,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "1900-01-01 00:00:00","2000-01-01 00:00:00",0,1,0,null,                 ''],
                ],
            ],
            'arsse_article_contents' => [
                'columns' => ["id", "content"],
                'rows' => [
                    [1,   'First article'],
                    [2,   'Second article'],
                    [3,   'third article'],
                    [4,   ''],
                    [5,   ''],
                    [6,   ''],
                    [7,   ''],
                    [8,   ''],
                    [19,  ''],
                    [20,  ''],
                    [501,'First article'],
                    [502,'Second article'],
                    [519,''],
                    [520,''],
                    [101, '<p>Article content 1</p>'],
                    [102, '<p>Article content 2</p>'],
                    [103, '<p>Article content 3</p>'],
                    [104, '<p>Article content 4</p>'],
                    [105, '<p>Article content 5</p>'],
                    [119, ''],
                    [120, ''],
                    [203, 'third article'],
                    [204, ''],
                    [205, ''],
                    [206, ''],
                    [207, ''],
                    [208, ''],
                    [801,'<p>Scraped content 1</p>'],
                    [802,'<p>Article content 2</p>'],
                ],
            ],
            'arsse_editions' => [
                'columns' => ["id", "article"],
                'rows'    => [
                    [   1,  1],
                    [   2,  2],
                    [   3,  3],
                    [   4,  4],
                    [   5,  5],
                    [   6,  6],
                    [   7,  7],
                    [   8,  8],
                    [  19, 19],
                    [  20, 20],
                    [1001, 20],
                    [ 101,101],
                    [ 102,102],
                    [ 202,102],
                    [ 103,103],
                    [ 203,103],
                    [ 104,104],
                    [ 204,104],
                    [ 105,105],
                    [ 205,105],
                    [ 305,105],
                    [ 501,501],
                    [ 119,119],
                    [ 120,120],
                    [1101,120],
                    [2203,203],
                    [2204,204],
                    [2205,205],
                    [2206,206],
                    [2207,207],
                    [2208,208],
                    [ 502,502],
                    [ 519,519],
                    [ 520,520],
                    [1501,520],
                    [ 801,801],
                    [ 802,802],
                    [ 902,802],
                    [ 999,999],
                ],
            ],
            'arsse_enclosures' => [
                'columns' => ["article", "url", "type"],
                'rows'    => [
                    [102,"http://example.com/text","text/plain"],
                    [103,"http://example.com/video","video/webm"],
                    [104,"http://example.com/image","image/svg+xml"],
                    [105,"http://example.com/audio","audio/ogg"],
                    [802,"http://example.com/text","text/plain"],
                ],
            ],
            'arsse_categories' => [ // author-supplied categories
                'columns' => ["article", "name"],
                'rows'    => [
                    [19, "Fascinating"],
                    [19, "Logical"],
                    [20, "Interesting"],
                    [20, "Logical"],
                    [119,"Fascinating"],
                    [119,"Logical"],
                    [120,"Interesting"],
                    [120,"Logical"],
                    [519,"Fascinating"],
                    [519,"Logical"],
                    [520,"Interesting"],
                    [520,"Logical"],
                ],
            ],
            'arsse_labels' => [ // labels applied to articles
                'columns' => ["id", "owner", "name"],
                'rows'    => [
                    [1,"john.doe@example.com","Interesting"],
                    [2,"john.doe@example.com","Fascinating"],
                    [3,"jane.doe@example.com","Boring"],
                    [4,"john.doe@example.com","Lonely"],
                ],
            ],
            'arsse_label_members' => [
                'columns' => ["label", "article", "assigned", "modified"],
                'rows'    => [
                    [1,  1,1,'2000-01-01 00:00:00'],
                    [2,  1,1,'2000-01-01 00:00:00'],
                    [1, 19,1,'2000-01-01 00:00:00'],
                    [2, 20,1,'2000-01-01 00:00:00'],
                    [1,  5,0,'2000-01-01 00:00:00'],
                    [2,  5,1,'2000-01-01 00:00:00'],
                    [4,  7,0,'2000-01-01 00:00:00'],
                    [4,  8,1,'2015-01-01 00:00:00'],
                    [1,999,1,'2000-01-01 00:00:00'],
                ],
            ],
        ];
        $this->matches = [
            [
                'id'                 => 101,
                'url'                => 'http://example.com/1',
                'title'              => 'Article title 1',
                'subscription_title' => "Feed 11",
                'author'             => '',
                'content'            => '<p>Article content 1</p>',
                'guid'               => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'published_date'     => '2000-01-01 00:00:00',
                'edited_date'        => '2000-01-01 00:00:01',
                'modified_date'      => '2000-01-01 01:00:00',
                'unread'             => 1,
                'starred'            => 0,
                'edition'            => 101,
                'subscription'       => 8,
                'fingerprint'        => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'media_url'          => null,
                'media_type'         => null,
                'note'               => "",
            ],
            [
                'id'                 => 102,
                'url'                => 'http://example.com/2',
                'title'              => 'Article title 2',
                'subscription_title' => "Feed 11",
                'author'             => '',
                'content'            => '<p>Article content 2</p>',
                'guid'               => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'published_date'     => '2000-01-02 00:00:00',
                'edited_date'        => '2000-01-02 00:00:02',
                'modified_date'      => '2000-01-02 02:00:00',
                'unread'             => 0,
                'starred'            => 0,
                'edition'            => 202,
                'subscription'       => 8,
                'fingerprint'        => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'media_url'          => "http://example.com/text",
                'media_type'         => "text/plain",
                'note'               => "Note 2",
            ],
            [
                'id'                 => 103,
                'url'                => 'http://example.com/3',
                'title'              => 'Article title 3',
                'subscription_title' => "Subscription 9",
                'author'             => '',
                'content'            => '<p>Article content 3</p>',
                'guid'               => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'published_date'     => '2000-01-03 00:00:00',
                'edited_date'        => '2000-01-03 00:00:03',
                'modified_date'      => '2000-01-03 03:00:00',
                'unread'             => 1,
                'starred'            => 1,
                'edition'            => 203,
                'subscription'       => 9,
                'fingerprint'        => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
                'media_url'          => "http://example.com/video",
                'media_type'         => "video/webm",
                'note'               => "Note 3",
            ],
            [
                'id'                 => 104,
                'url'                => 'http://example.com/4',
                'title'              => 'Article title 4',
                'subscription_title' => "Subscription 9",
                'author'             => '',
                'content'            => '<p>Article content 4</p>',
                'guid'               => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'published_date'     => '2000-01-04 00:00:00',
                'edited_date'        => '2000-01-04 00:00:04',
                'modified_date'      => '2000-01-04 04:00:00',
                'unread'             => 0,
                'starred'            => 1,
                'edition'            => 204,
                'subscription'       => 9,
                'fingerprint'        => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
                'media_url'          => "http://example.com/image",
                'media_type'         => "image/svg+xml",
                'note'               => "Note 4",
            ],
            [
                'id'                 => 105,
                'url'                => 'http://example.com/5',
                'title'              => 'Article title 5',
                'subscription_title' => "Feed 13",
                'author'             => '',
                'content'            => '<p>Article content 5</p>',
                'guid'               => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'published_date'     => '2000-01-05 00:00:00',
                'edited_date'        => '2000-01-05 00:00:05',
                'modified_date'      => '2000-01-05 05:00:00',
                'unread'             => 1,
                'starred'            => 0,
                'edition'            => 305,
                'subscription'       => 10,
                'fingerprint'        => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
                'media_url'          => "http://example.com/audio",
                'media_type'         => "audio/ogg",
                'note'               => "",
            ],
        ];
        $this->fields = [
            "id", "subscription", "modified_date", "marked_date", "unread", "starred", "hidden", "edition", "edited_date",
            "url", "title", "subscription_title", "author", "guid", "published_date", "fingerprint",
            "folder", "top_folder", "folder_name", "top_folder_name",
            "content", "media_url", "media_type",
            "note",
        ];
        $this->checkTables = ['arsse_articles' => ["id", "read", "starred", "hidden", "marked", "note"]];
        $this->user = "john.doe@example.net";
    }

    protected function tearDownSeriesArticle(): void {
        unset($this->data, $this->matches, $this->fields, $this->checkTables, $this->user);
    }


    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    #[DataProvider("provideContextMatches")]
    public function testListArticlesCheckingContext(RootContext $c, array $exp): void {
        $ids = array_column($ids = Arsse::$db->articleList("john.doe@example.com", $c, ["id"], ["id"])->getAll(), "id");
        $ids = array_map(function($v) {
            return (int) $v;
        }, $ids);
        sort($ids);
        sort($exp);
        $this->assertEquals($exp, $ids);
    }

    public static function provideContextMatches(): iterable {
        $setSize = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue();
        return [
            'Blank context'                                              => [new Context, [1,2,3,4,5,6,7,8,19,20]],
            'Folder tree'                                                => [(new Context)->folder(1), [5,6,7,8]],
            'Entire folder tree'                                         => [(new Context)->folder(0), [1,2,3,4,5,6,7,8,19,20]],
            'Leaf folder'                                                => [(new Context)->folder(6), [7,8]],
            'Multiple folder trees'                                      => [(new Context)->folders([1,5]), [5,6,7,8,19,20]],
            'Multiple folder trees including root'                       => [(new Context)->folders([0,1,5]), [1,2,3,4,5,6,7,8,19,20]],
            'Shallow folder'                                             => [(new Context)->folderShallow(1), [5,6]],
            'Root folder only'                                           => [(new Context)->folderShallow(0), [1,2,3,4]],
            'Multiple shallow folders'                                   => [(new Context)->foldersShallow([1,6]), [5,6,7,8]],
            'Subscription'                                               => [(new Context)->subscription(5), [19,20]],
            'Multiple subscriptions'                                     => [(new Context)->subscriptions([4,5]), [7,8,19,20]],
            'Unread'                                                     => [(new Context)->subscription(5)->unread(true), [20]],
            'Read'                                                       => [(new Context)->subscription(5)->unread(false), [19]],
            'Starred'                                                    => [(new Context)->starred(true), [1,20]],
            'Unstarred'                                                  => [(new Context)->starred(false), [2,3,4,5,6,7,8,19]],
            'Starred and Read'                                           => [(new Context)->starred(true)->unread(false), [1]],
            'Starred and Read in subscription'                           => [(new Context)->starred(true)->unread(false)->subscription(5), []],
            'Annotated'                                                  => [(new Context)->annotated(true), [2]],
            'Not annotated'                                              => [(new Context)->annotated(false), [1,3,4,5,6,7,8,19,20]],
            'Hidden'                                                     => [(new Context)->hidden(true), [6]],
            'Not hidden'                                                 => [(new Context)->hidden(false), [1,2,3,4,5,7,8,19,20]],
            'Labelled'                                                   => [(new Context)->labelled(true), [1,5,8,19,20]],
            'Not labelled'                                               => [(new Context)->labelled(false), [2,3,4,6,7]],
            'Not after edition 999'                                      => [(new Context)->subscription(5)->editionRange(null, 999), [19]],
            'Not after edition 19'                                       => [(new Context)->subscription(5)->editionRange(null, 19), [19]],
            'Not before edition 999'                                     => [(new Context)->subscription(5)->editionRange(999, null), [20]],
            'Not before edition 1001'                                    => [(new Context)->subscription(5)->editionRange(1001, null), [20]],
            'Not after article 3'                                        => [(new Context)->articleRange(null, 3), [1,2,3]],
            'Not before article 19'                                      => [(new Context)->articleRange(19, null), [19,20]],
            'Modified in database since 2005'                            => [(new Context)->modifiedRange("2005-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Modified in database since 2010'                            => [(new Context)->modifiedRange("2010-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Not modified in database since 2005'                        => [(new Context)->modifiedRange(null, "2005-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Not modified in database since 2000'                        => [(new Context)->modifiedRange(null, "2000-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Added to database since 1905'                               => [(new Context)->addedRange("1905-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Added to database since 1910'                               => [(new Context)->addedRange("1910-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Not added to database since 1905'                           => [(new Context)->addedRange(null, "1905-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Not added to database since 1900'                           => [(new Context)->addedRange(null, "1900-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Published since 1805'                                       => [(new Context)->publishedRange("1805-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Published since 1810'                                       => [(new Context)->publishedRange("1810-01-01T00:00:00Z", null), [2,4,6,8,20]],
            'Not Published since 1805'                                   => [(new Context)->publishedRange(null, "1805-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Not Published since 1800'                                   => [(new Context)->publishedRange(null, "1800-01-01T00:00:00Z"), [1,3,5,7,19]],
            'Marked or labelled since 2014'                              => [(new Context)->markedRange("2014-01-01T00:00:00Z", null), [8,19]],
            'Marked or labelled since 2010'                              => [(new Context)->markedRange("2010-01-01T00:00:00Z", null), [2,4,6,8,19,20]],
            'Not marked or labelled since 2014'                          => [(new Context)->markedRange(null, "2014-01-01T00:00:00Z"), [1,2,3,4,5,6,7,20]],
            'Not marked or labelled since 2005'                          => [(new Context)->markedRange(null, "2005-01-01T00:00:00Z"), [1,3,5,7]],
            'Marked or labelled between 2000 and 2015'                   => [(new Context)->markedRange("2000-01-01T00:00:00Z", "2015-12-31T23:59:59Z"), [1,2,3,4,5,6,7,8,20]],
            'Marked or labelled in 2010'                                 => [(new Context)->markedRange("2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"), [2,4,6,20]],
            'Paged results'                                              => [(new Context)->limit(2)->editionRange(4, null), [4,5]],
            'With label ID 1'                                            => [(new Context)->label(1), [1,19]],
            'With label ID 2'                                            => [(new Context)->label(2), [1,5,20]],
            'With label ID 1 or 2'                                       => [(new Context)->labels([1,2]), [1,5,19,20]],
            'With label "Interesting"'                                   => [(new Context)->labelName("Interesting"), [1,19]],
            'With label "Fascinating"'                                   => [(new Context)->labelName("Fascinating"), [1,5,20]],
            'With label "Interesting" or "Fascinating"'                  => [(new Context)->labelNames(["Interesting","Fascinating"]), [1,5,19,20]],
            'Article ID 20'                                              => [(new Context)->article(20), [20]],
            'Edition ID 20'                                              => [(new Context)->edition(20), []],
            'Edition ID 1001'                                            => [(new Context)->edition(1001), [20]],
            'Multiple articles'                                          => [(new Context)->articles([1,20,50]), [1,20]],
            'Multiple starred articles'                                  => [(new Context)->articles([1,2,3])->starred(true), [1]],
            'Multiple unstarred articles'                                => [(new Context)->articles([1,2,3])->starred(false), [2,3]],
            'Multiple articles'                                          => [(new Context)->articles([1,20,50]), [1,20]],
            'Multiple editions'                                          => [(new Context)->editions([1,1001,50]), [1,20]],
            '150 articles'                                               => [(new Context)->articles(range(1, $setSize * 3)), [1,2,3,4,5,6,7,8,19,20]],
            'Search title or content 1'                                  => [(new Context)->searchTerms(["Article"]), [1,2,3]],
            'Search title or content 2'                                  => [(new Context)->searchTerms(["one", "first"]), [1]],
            'Search title or content 3'                                  => [(new Context)->searchTerms(["one first"]), []],
            'Search title 1'                                             => [(new Context)->titleTerms(["two"]), [2]],
            'Search title 2'                                             => [(new Context)->titleTerms(["title two"]), [2]],
            'Search title 3'                                             => [(new Context)->titleTerms(["two", "title"]), [2]],
            'Search title 4'                                             => [(new Context)->titleTerms(["two title"]), []],
            'Search note 1'                                              => [(new Context)->annotationTerms(["some"]), [2]],
            'Search note 2'                                              => [(new Context)->annotationTerms(["some Note"]), [2]],
            'Search note 3'                                              => [(new Context)->annotationTerms(["note", "some"]), [2]],
            'Search note 4'                                              => [(new Context)->annotationTerms(["some", "sauce"]), []],
            'Search author 1'                                            => [(new Context)->authorTerms(["doe"]), [4,5,6,7]],
            'Search author 2'                                            => [(new Context)->authorTerms(["jane doe"]), [6,7]],
            'Search author 3'                                            => [(new Context)->authorTerms(["doe", "jane"]), [6,7]],
            'Search author 4'                                            => [(new Context)->authorTerms(["doe jane"]), []],
            'Folder tree 1 excluding subscription 4'                     => [(new Context)->not->subscription(4)->folder(1), [5,6]],
            'Folder tree 1 excluding articles 7 and 8'                   => [(new Context)->folder(1)->not->articles([7,8]), [5,6]],
            'Folder tree 1 excluding no articles'                        => [(new Context)->folder(1)->not->articles([]), [5,6,7,8]],
            'Folder tree 1 excluding no labels'                          => [(new Context)->folder(1)->not->labels([]), [5,6,7,8]],
            'Folder tree 1 excluding no tags'                            => [(new Context)->folder(1)->not->tags([]), [5,6,7,8]],
            'Marked or labelled between 2000 and 2015 excluding in 2010' => [(new Context)->markedRange("2000-01-01T00:00:00Z", "2015-12-31T23:59:59")->not->markedRange("2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"), [1,3,5,7,8]],
            'Search with exclusion'                                      => [(new Context)->searchTerms(["Article"])->not->searchTerms(["one", "two"]), [3]],
            'Excluded folder tree'                                       => [(new Context)->not->folder(1), [1,2,3,4,19,20]],
            'Excluding label ID 2'                                       => [(new Context)->not->label(2), [2,3,4,6,7,8,19]],
            'Excluding label "Fascinating"'                              => [(new Context)->not->labelName("Fascinating"), [2,3,4,6,7,8,19]],
            'Search 501 terms'                                           => [(new Context)->searchTerms(array_merge(range(1, 500), [str_repeat("a", 1000)])), []],
            'With tag ID 1'                                              => [(new Context)->tag(1), [5,6,7,8]],
            'With tag ID 5'                                              => [(new Context)->tag(5), [7,8,19,20]],
            'With tag ID 1 or 5'                                         => [(new Context)->tags([1,5]), [5,6,7,8,19,20]],
            'With tag "Technology"'                                      => [(new Context)->tagName("Technology"), [5,6,7,8]],
            'With tag "Politics"'                                        => [(new Context)->tagName("Politics"), [7,8,19,20]],
            'With tag "Technology" or "Politics"'                        => [(new Context)->tagNames(["Technology","Politics"]), [5,6,7,8,19,20]],
            'Excluding tag ID 1'                                         => [(new Context)->not->tag(1), [1,2,3,4,19,20]],
            'Excluding tag ID 5'                                         => [(new Context)->not->tag(5), [1,2,3,4,5,6]],
            'Excluding tag "Technology"'                                 => [(new Context)->not->tagName("Technology"), [1,2,3,4,19,20]],
            'Excluding tag "Politics"'                                   => [(new Context)->not->tagName("Politics"), [1,2,3,4,5,6]],
            'Excluding tags ID 1 and 5'                                  => [(new Context)->not->tags([1,5]), [1,2,3,4]],
            'Excluding tags "Technology" and "Politics"'                 => [(new Context)->not->tagNames(["Technology","Politics"]), [1,2,3,4]],
            'Excluding entire folder tree'                               => [(new Context)->not->folder(0), []],
            'Excluding multiple folder trees'                            => [(new Context)->not->folders([1,5]), [1,2,3,4]],
            'Excluding multiple folder trees including root'             => [(new Context)->not->folders([0,1,5]), []],
            'Before article 3'                                           => [(new Context)->not->articleRange(3, null), [1,2]],
            'Before article 19'                                          => [(new Context)->not->articleRange(null, 19), [20]],
            'Marked or labelled in 2010 or 2015'                         => [(new Context)->markedRanges([["2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", "2015-12-31T23:59:59Z"]]), [2,4,6,8,20]],
            'Not marked or labelled in 2010 or 2015'                     => [(new Context)->not->markedRanges([["2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", "2015-12-31T23:59:59Z"]]), [1,3,5,7,19]],
            'Marked or labelled prior to 2010 or since 2015'             => [(new Context)->markedRanges([[null, "2009-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", null]]), [1,3,5,7,8,19]],
            'Not marked or labelled prior to 2010 or since 2015'         => [(new Context)->not->markedRanges([[null, "2009-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", null]]), [2,4,6,20]],
            'Modified in 2010 or 2015'                                   => [(new Context)->modifiedRanges([["2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", "2015-12-31T23:59:59Z"]]), [2,4,6,8,20]],
            'Not modified in 2010 or 2015'                               => [(new Context)->not->modifiedRanges([["2010-01-01T00:00:00Z", "2010-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", "2015-12-31T23:59:59Z"]]), [1,3,5,7,19]],
            'Modified prior to 2010 or since 2015'                       => [(new Context)->modifiedRanges([[null, "2009-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", null]]), [1,3,5,7,19]],
            'Not modified prior to 2010 or since 2015'                   => [(new Context)->not->modifiedRanges([[null, "2009-12-31T23:59:59Z"], ["2015-01-01T00:00:00Z", null]]), [2,4,6,8,20]],
            'Added in 1910 or 1915'                                      => [(new Context)->addedRanges([["1910-01-01T00:00:00Z", "1910-12-31T23:59:59Z"], ["1915-01-01T00:00:00Z", "1915-12-31T23:59:59Z"]]), [2,4,6,8,20]],
            'Not added in 1910 or 1915'                                  => [(new Context)->not->addedRanges([["1910-01-01T00:00:00Z", "1910-12-31T23:59:59Z"], ["1915-01-01T00:00:00Z", "1915-12-31T23:59:59Z"]]), [1,3,5,7,19]],
            'Added prior to 1910 or since 1915'                          => [(new Context)->addedRanges([[null, "1909-12-31T23:59:59Z"], ["1915-01-01T00:00:00Z", null]]), [1,3,5,7,19]],
            'Not added prior to 1910 or since 1915'                      => [(new Context)->not->addedRanges([[null, "1909-12-31T23:59:59Z"], ["1915-01-01T00:00:00Z", null]]), [2,4,6,8,20]],
            'Published in 1810 or 1815'                                  => [(new Context)->publishedRanges([["1810-01-01T00:00:00Z", "1810-12-31T23:59:59Z"], ["1815-01-01T00:00:00Z", "1815-12-31T23:59:59Z"]]), [2,4,6,8,20]],
            'Not published in 1810 or 1815'                              => [(new Context)->not->publishedRanges([["1810-01-01T00:00:00Z", "1810-12-31T23:59:59Z"], ["1815-01-01T00:00:00Z", "1815-12-31T23:59:59Z"]]), [1,3,5,7,19]],
            'Published prior to 1810 or since 1815'                      => [(new Context)->publishedRanges([[null, "1809-12-31T23:59:59Z"], ["1815-01-01T00:00:00Z", null]]), [1,3,5,7,19]],
            'Not published prior to 1810 or since 1815'                  => [(new Context)->not->publishedRanges([[null, "1809-12-31T23:59:59Z"], ["1815-01-01T00:00:00Z", null]]), [2,4,6,8,20]],
            'Either read or hidden'                                      => [(new UnionContext((new Context)->unread(false), (new Context)->hidden(true))), [1, 6, 19]],
        ];
    }

    //#[CoversMethod(Database::class, "editionArticle")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testRetrieveArticleIdsForEditions(): void {
        $exp = [
            1    => 1,
            2    => 2,
            3    => 3,
            4    => 4,
            5    => 5,
            6    => 6,
            7    => 7,
            8    => 8,
            19   => 19,
            20   => 20,
            101  => 101,
            102  => 102,
            103  => 103,
            104  => 104,
            105  => 105,
            119  => 119,
            120  => 120,
            202  => 102,
            203  => 103,
            204  => 104,
            205  => 105,
            305  => 105,
            501  => 501,
            502  => 502,
            519  => 519,
            520  => 520,
            801  => 801,
            802  => 802,
            902  => 802,
            999  => 999,
            1001 => 20,
        ];
        $act = Arsse::$db->editionArticle(...range(1, 1001));
        $act = array_map(function($v) {
            return (int) $v;
        }, $act);
        $this->assertEquals($exp, $act);
    }


    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testListArticlesOfAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->folder(1));
    }

    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")] 
    public function testListArticlesOfAMissingSubscription(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->subscription(1));
    }

    public function testListArticlesOfADeletedSubscription(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->subscription(16));
    }

    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")] 
    public function testListArticlesCheckingProperties(): void {
        $this->user = "john.doe@example.org";
        // check that the different fieldset groups return the expected columns
        foreach ($this->fields as $column) {
            $test = array_keys(Arsse::$db->articleList($this->user, (new Context)->article(101), [$column])->getRow());
            $this->assertEquals([$column], $test);
        }
        // check that an unknown field is silently ignored
        $columns = array_merge($this->fields, ["unknown_column", "bogus_column"]);
        $test = array_keys(Arsse::$db->articleList($this->user, (new Context)->article(101), $columns)->getRow());
        $this->assertEquals($this->fields, $test);
    }

    #[DataProvider("provideOrderedLists")]
    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testListArticlesCheckingOrder(array $sortCols, array $exp): void {
        $act = ValueInfo::normalize(array_column(iterator_to_array(Arsse::$db->articleList("john.doe@example.com", null, ["id"], $sortCols)), "id"), ValueInfo::T_INT | ValueInfo::M_ARRAY);
        $this->assertSame($exp, $act);
    }

    public static function provideOrderedLists(): iterable {
        return [
            [["id"], [1,2,3,4,5,6,7,8,19,20]],
            [["id asc"], [1,2,3,4,5,6,7,8,19,20]],
            [["id desc"], [20,19,8,7,6,5,4,3,2,1]],
            [["edition"], [1,2,3,4,5,6,7,8,19,20]],
            [["edition asc"], [1,2,3,4,5,6,7,8,19,20]],
            [["edition desc"], [20,19,8,7,6,5,4,3,2,1]],
            [["id", "edition desk"], [1,2,3,4,5,6,7,8,19,20]],
            [["id", "editio"], [1,2,3,4,5,6,7,8,19,20]],
        ];
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkNothing(): void {
        $this->assertSame(0, Arsse::$db->articleMark($this->user, []));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnread(): void {
        Arsse::$db->articleMark($this->user, ['read' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20][1] = 0;
        $state['arsse_articles']['rows'][20][4] = $now;
        $state['arsse_articles']['rows'][22][1] = 0;
        $state['arsse_articles']['rows'][22][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesRead(): void {
        Arsse::$db->articleMark($this->user, ['read' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,1,0,0,$now,"ook"];
        $state['arsse_articles']['rows'][21] = [203,1,1,0,$now,"ack"];
        $state['arsse_articles']['rows'][23] = [205,1,0,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,0,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,1,0,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,1,0,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnstarred(): void {
        Arsse::$db->articleMark($this->user, ['starred' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][21][2] = 0;
        $state['arsse_articles']['rows'][21][4] = $now;
        $state['arsse_articles']['rows'][22][2] = 0;
        $state['arsse_articles']['rows'][22][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesStarred(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,0,1,0,$now,"ook"];
        $state['arsse_articles']['rows'][20] = [120,1,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][23] = [205,0,1,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,0,1,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnreadAndUnstarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,0,0,0,$now,"eek"];
        $state['arsse_articles']['rows'][21] = [203,0,0,0,$now,"ack"];
        $state['arsse_articles']['rows'][22] = [204,0,0,0,$now,"ach"];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesReadAndStarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => true,'starred' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,1,1,0,$now,"ook"];
        $state['arsse_articles']['rows'][20] = [120,1,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][21] = [203,1,1,0,$now,"ack"];
        $state['arsse_articles']['rows'][23] = [205,1,1,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,1,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,1,1,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,1,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnreadAndStarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,0,1,0,$now,"ook"];
        $state['arsse_articles']['rows'][20] = [120,0,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][22] = [204,0,1,0,$now,"ach"];
        $state['arsse_articles']['rows'][23] = [205,0,1,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,0,1,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesReadAndUnstarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => true,'starred' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,1,0,0,$now,"ook"];
        $state['arsse_articles']['rows'][21] = [203,1,0,0,$now,"ack"];
        $state['arsse_articles']['rows'][22] = [204,1,0,0,$now,"ach"];
        $state['arsse_articles']['rows'][23] = [205,1,0,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,0,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,1,0,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,1,0,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testSetNoteForAllArticles(): void {
        Arsse::$db->articleMark($this->user, ['note' => "New note"]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19] = [119,0,0,0,$now,"New note"];
        $state['arsse_articles']['rows'][20] = [120,1,0,0,$now,"New note"];
        $state['arsse_articles']['rows'][21] = [203,0,1,0,$now,"New note"];
        $state['arsse_articles']['rows'][22] = [204,1,1,0,$now,"New note"];
        $state['arsse_articles']['rows'][23] = [205,0,0,0,$now,"New note"];
        $state['arsse_articles']['rows'][24] = [206,0,0,0,$now,"New note"];
        $state['arsse_articles']['rows'][25] = [207,0,0,0,$now,"New note"];
        $state['arsse_articles']['rows'][26] = [208,0,0,0,$now,"New note"];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkATreeFolder(): void {
        Arsse::$db->articleMark($this->user, ['read' => true], (new Context)->folder(7));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][23] = [205,1,0,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,0,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,1,0,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,1,0,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkALeafFolder(): void {
        Arsse::$db->articleMark($this->user, ['read' => true], (new Context)->folder(8));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][23] = [205,1,0,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,0,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAMissingFolder(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read' => true], (new Context)->folder(42));
    }

    public function testMarkASubscription(): void {
        Arsse::$db->articleMark($this->user, ['read' => true], (new Context)->subscription(13));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][23] = [205,1,0,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,1,0,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAMissingSubscription(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['read' => true], (new Context)->subscription(1));
    }

    public function testMarkADeletedSubscription(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark("john.doe@example.com", ['read' => true], (new Context)->subscription(16));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAnArticle(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->article(120));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20][2] = 1;
        $state['arsse_articles']['rows'][20][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleArticles(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->articles([202,204,207,120]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,1,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleArticlessUnreadAndStarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => true], (new Context)->articles([202,204,207,120]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,0,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][22] = [204,0,1,0,$now,"ach"];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkTooManyMultipleArticles(): void {
        $setSize = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue();
        $this->assertSame(7, Arsse::$db->articleMark("john.doe@example.com", ['read' => false,'starred' => true], (new Context)->articles(range(3, $setSize * 3))));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAMissingArticle(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->article(1));
    }

    public function testMarkAnArticleOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark("john.doe@example.com", ['starred' => true], (new Context)->article(999));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAnEdition(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->edition(1101));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20][2] = 1;
        $state['arsse_articles']['rows'][20][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleEditions(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->editions([2202,2204,2207,120]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,1,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleMissingEditions(): void {
        $this->assertSame(0, Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->editions([56458, 1851855])));
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleEditionsUnread(): void {
        Arsse::$db->articleMark($this->user, ['read' => false], (new Context)->editions([2202,2204,2207,1101]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,0,0,0,$now,"eek"];
        $state['arsse_articles']['rows'][22] = [204,0,1,0,$now,"ach"];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleEditionsUnreadWithStale(): void {
        Arsse::$db->articleMark($this->user, ['read' => false], (new Context)->editions([2202,2204,2207,120]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][22][1] = 0;
        $state['arsse_articles']['rows'][22][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleEditionsUnreadAndStarredWithStale(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => true], (new Context)->editions([2202,2204,2207,120]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20] = [120,1,1,0,$now,"eek"];
        $state['arsse_articles']['rows'][22] = [204,0,1,0,$now,"ach"];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkTooManyMultipleEditions(): void {
        $this->assertSame(7, Arsse::$db->articleMark("john.doe@example.com", ['read' => false,'starred' => true], (new Context)->editions(range(3, 51))));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionUnread(): void {
        Arsse::$db->articleMark($this->user, ['read' => false], (new Context)->edition(120)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionStarred(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->edition(120));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20][2] = 1;
        $state['arsse_articles']['rows'][20][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionUnreadAndStarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => true], (new Context)->edition(120)); // only starred is changed
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][20][2] = 1;
        $state['arsse_articles']['rows'][20][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionUnreadAndUnstarred(): void {
        Arsse::$db->articleMark($this->user, ['read' => false,'starred' => false], (new Context)->edition(120)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAMissingEdition(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->edition(2));
    }

    public function testMarkAnEditionOfADeletedSubscription(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleMark("john.doe@example.com", ['starred' => false], (new Context)->edition(999));
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkByOldestEdition(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->editionRange(2205, null));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][23] = [205,0,1,0,$now,""];
        $state['arsse_articles']['rows'][24] = [206,0,1,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $state['arsse_articles']['rows'][26] = [208,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkByLatestEdition(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->editionRange(null, 120));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19][2] = 1;
        $state['arsse_articles']['rows'][19][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkByLastMarked(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->markedRange('2017-01-01T00:00:00Z', null));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][19][2] = 1;
        $state['arsse_articles']['rows'][19][4] = $now;
        $state['arsse_articles']['rows'][20][2] = 1;
        $state['arsse_articles']['rows'][20][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkByNotLastMarked(): void {
        Arsse::$db->articleMark($this->user, ['starred' => true], (new Context)->markedRange(null, '2000-01-01T00:00:00Z'));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][23] = [205,0,1,0,$now,""];
        $state['arsse_articles']['rows'][25] = [207,0,1,0,$now,""];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleCount")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testCountArticles(): void {
        $setSize = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue();
        $this->assertSame(2, Arsse::$db->articleCount("john.doe@example.com", (new Context)->starred(true)));
        $this->assertSame(4, Arsse::$db->articleCount("john.doe@example.com", (new Context)->folder(1)));
        $this->assertSame(1, Arsse::$db->articleCount("jane.doe@example.com", (new Context)->starred(true)));
        $this->assertSame(10, Arsse::$db->articleCount("john.doe@example.com", (new Context)->articles(range(1, $setSize * 3))));
    }

    //#[CoversMethod(Database::class, "articleStarred")]
    public function testFetchStarredCounts(): void {
        $exp1 = ['total' => 2, 'unread' => 1, 'read' => 1];
        $exp2 = ['total' => 0, 'unread' => 0, 'read' => 0];
        $this->assertEquals($exp1, Arsse::$db->articleStarred("john.doe@example.com"));
        $this->assertEquals($exp2, Arsse::$db->articleStarred("jane.doe@example.com"));
    }

    //#[CoversMethod(Database::class, "editionLatest")]
    public function testFetchLatestEdition(): void {
        $this->assertSame(2208, Arsse::$db->editionLatest($this->user));
        $this->assertSame(2204, Arsse::$db->editionLatest($this->user, (new Context)->subscription(12)));
        $this->assertSame(5, Arsse::$db->editionLatest("john.doe@example.com", (new Context)->subscription(3)->hidden(false)));
    }

    //#[CoversMethod(Database::class, "editionLatest")]
    public function testFetchLatestEditionOfMissingSubscription(): void {
        $this->assertException("idMissing", "Db", "ExceptionInput");
        Arsse::$db->editionLatest($this->user, (new Context)->subscription(1));
    }

    //#[CoversMethod(Database::class, "articleLabelsGet")]
    public function testListTheLabelsOfAnArticle(): void {
        $this->assertEquals([1,2], Arsse::$db->articleLabelsGet("john.doe@example.com", 1));
        $this->assertEquals([2], Arsse::$db->articleLabelsGet("john.doe@example.com", 5));
        $this->assertEquals([], Arsse::$db->articleLabelsGet("john.doe@example.com", 2));
        $this->assertEquals(["Fascinating","Interesting"], Arsse::$db->articleLabelsGet("john.doe@example.com", 1, true));
        $this->assertEquals(["Fascinating"], Arsse::$db->articleLabelsGet("john.doe@example.com", 5, true));
        $this->assertEquals([], Arsse::$db->articleLabelsGet("john.doe@example.com", 2, true));
    }

    //#[CoversMethod(Database::class, "articleLabelsGet")]
    public function testListTheLabelsOfAMissingArticle(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleLabelsGet($this->user, 101);
    }

    //#[CoversMethod(Database::class, "articleCategoriesGet")]
    public function testListTheCategoriesOfAnArticle(): void {
        $exp = ["Fascinating", "Logical"];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 119));
        $exp = ["Interesting", "Logical"];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 120));
        $exp = [];
        $this->assertSame($exp, Arsse::$db->articleCategoriesGet($this->user, 204));
    }

    //#[CoversMethod(Database::class, "articleCategoriesGet")]
    public function testListTheCategoriesOfAMissingArticle(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->articleCategoriesGet($this->user, 101);
    }

    #[DataProvider("provideArrayContextOptions")]
    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testUseTooFewValuesInArrayContext(string $option): void {
        $this->assertException("tooShort", "Db", "ExceptionInput");
        Arsse::$db->articleList($this->user, (new Context)->$option([]));
    }

    public static function provideArrayContextOptions(): iterable {
        foreach ([
            "articles", "editions",
            "subscriptions", "foldersShallow", "folders",
            "tags", "tagNames", "labels", "labelNames",
            "searchTerms", "authorTerms", "annotationTerms",
            "modifiedRanges", "markedRanges",
        ] as $method) {
            yield [$method];
        }
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesNotHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['hidden' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][10][3] = 0;
        $state['arsse_articles']['rows'][10][4] = $now;
        $state['arsse_articles']['rows'][11][3] = 0;
        $state['arsse_articles']['rows'][11][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['hidden' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][12][3] = 1;
        $state['arsse_articles']['rows'][12][4] = $now;
        $state['arsse_articles']['rows'][13][3] = 1;
        $state['arsse_articles']['rows'][13][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnreadAndNotHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => false, 'hidden' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][10] = [501,0,1,0,$now,''];
        $state['arsse_articles']['rows'][11] = [502,0,0,0,$now,''];
        $state['arsse_articles']['rows'][13] = [520,0,0,0,$now,''];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesReadAndHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => true, 'hidden' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][10] = [501,1,1,1,$now,''];
        $state['arsse_articles']['rows'][12] = [519,1,0,1,$now,''];
        $state['arsse_articles']['rows'][13] = [520,1,0,1,$now,''];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesUnreadAndHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => false,'hidden' => true]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][11] = [502,0,0,1,$now,''];
        $state['arsse_articles']['rows'][12] = [519,0,0,1,$now,''];
        $state['arsse_articles']['rows'][13] = [520,0,0,1,$now,''];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAllArticlesReadAndNotHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => true,'hidden' => false]);
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][10] = [501,1,1,0,$now,''];
        $state['arsse_articles']['rows'][11] = [502,1,0,0,$now,''];
        $state['arsse_articles']['rows'][12] = [519,1,0,0,$now,''];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkMultipleEditionsUnreadAndHiddenWithStale(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => false,'hidden' => true], (new Context)->editions([501,502,519,520]));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][11] = [502,0,0,1,$now,''];
        $state['arsse_articles']['rows'][12] = [519,0,0,1,$now,''];
        $state['arsse_articles']['rows'][13] = [520,1,0,1,$now,''];
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['hidden' => true], (new Context)->edition(520));
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][13][3] = 1;
        $state['arsse_articles']['rows'][13][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionUnreadAndHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => false,'hidden' => true], (new Context)->edition(520)); // only hidden is changed
        $now = Date::transform(time(), "sql");
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $state['arsse_articles']['rows'][13][3] = 1;
        $state['arsse_articles']['rows'][13][4] = $now;
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleMark")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testMarkAStaleEditionUnreadAndNotHidden(): void {
        Arsse::$db->articleMark("jane.doe@example.com", ['read' => false,'hidden' => false], (new Context)->edition(520)); // no changes occur
        $state = $this->primeExpectations($this->data, $this->checkTables);
        $this->compareExpectations(static::$drv, $state);
    }

    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testSelectScrapedContent(): void {
        $exp = [
            ['id' => 101, 'content' => "<p>Article content 1</p>"],
            ['id' => 102, 'content' => "<p>Article content 2</p>"],
        ];
        $this->assertResult($exp, Arsse::$db->articleList("john.doe@example.org", (new Context)->subscription(8), ["id", "content"]));
        $exp = [
            ['id' => 801, 'content' => "<p>Scraped content 1</p>"],
            ['id' => 802, 'content' => "<p>Article content 2</p>"],
        ];
        $this->assertResult($exp, Arsse::$db->articleList("jill.doe@example.com", (new Context)->subscription(15), ["id", "content"]));
    }

    //#[CoversMethod(Database::class, "articleList")]
    //#[CoversMethod(Database::class, "articleQuery")]
    //#[CoversMethod(Database::class, "articleValidateId")]
    //#[CoversMethod(Database::class, "articleValidateEdition")]
    //#[CoversMethod(Database::class, "articleColumns")]
    //#[CoversMethod(Database::class, "articleFilter")]
    public function testSearchScrapedContent(): void {
        $exp = [
            ['id' => 801, 'content' => "<p>Scraped content 1</p>"],
            ['id' => 802, 'content' => "<p>Article content 2</p>"],
        ];
        $this->assertResult($exp, Arsse::$db->articleList("jill.doe@example.com", (new Context)->subscription(15)->searchTerms(["article"]), ["id", "content"]));
        $exp = [
            ['id' => 801, 'content' => "<p>Scraped content 1</p>"],
        ];
        $this->assertResult($exp, Arsse::$db->articleList("jill.doe@example.com", (new Context)->subscription(15)->searchTerms(["scraped"]), ["id", "content"]));
    }
}
