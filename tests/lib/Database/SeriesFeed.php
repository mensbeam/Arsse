<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use Phake;

trait SeriesFeed {
    function setUpSeries() {
        $ts  = gmdate("Y-m-d H:i:s",strtotime("now - 1 minute"));
        $ts2 = gmdate("Y-m-d H:i:s",strtotime("now + 1 minute"));
        $ts3 = gmdate("Y-m-d H:i:s",strtotime("now"));
        $data = [
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'err_count'  => "int",
                    'err_msg'    => "str",
                    'modified'   => "datetime",
                    'next_fetch' => "datetime",
                ],
                'rows' => [
                    [1,"http://localhost:8000/Feed/Matching/3","Ook",0,"",$ts,$ts],
                    [2,"http://localhost:8000/Feed/Matching/1","Eek",5,"There was an error last time",$ts,$ts2], 
                    [3,"http://localhost:8000/Feed/Fetching/Error?code=404","Ack",0,"",$ts,$ts3],
                    [4,"http://localhost:8000/Feed/NextFetch/NotModified?t=".time(),"Ooook",0,"",$ts,$ts],
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
                    [1,1,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:00','<p>Article content 1</p>','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',$ts],
                    [2,1,'http://example.com/2','Article title 2','','2000-01-02 00:00:00','2000-01-02 00:00:00','<p>Article content 2</p>','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',$ts],
                    [3,1,'http://example.com/3','Article title 3','','2000-01-03 00:00:00','2000-01-03 00:00:00','<p>Article content 3</p>','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',$ts],
                    [4,1,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:00','<p>Article content 4</p>','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$ts],
                    [5,1,'http://example.com/5','Article title 5','','2000-01-05 00:00:00','2000-01-05 00:00:00','<p>Article content 5</p>','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',$ts],
                ]
            ],
            'arsse_editions' => [
                'columns' => [
                    'id'       => "int",
                    'article'  => "int",
                    'modified' => "datetime",
                ],
                'rows' => [
                    [1,1,$ts],
                    [2,2,$ts],
                    [3,3,$ts],
                    [4,4,$ts],
                    [5,5,$ts],
                ]
            ],
            'arsse_marks' => [
                'columns' => [
                    'id'      => "int",
                    'article' => "int",
                    'owner'   => "str",
                    'read'    => "bool",
                    'starred' => "bool",
                    'modified' => "datetime",
                ],
                'rows' => [
                    [1,1,"jane.doe@example.com",1,0,$ts],
                    [2,2,"jane.doe@example.com",1,0,$ts],
                    [3,3,"jane.doe@example.com",1,1,$ts],
                    [4,4,"jane.doe@example.com",1,0,$ts],
                    [5,5,"jane.doe@example.com",1,1,$ts],
                    [9, 1,"john.doe@example.com",1,0,$ts],
                    [10,3,"john.doe@example.com",1,0,$ts],
                    [11,4,"john.doe@example.com",0,1,$ts],
                ]
            ],
        ];
        // merge tables
        $this->data = array_merge($this->data, $data);
        $this->primeDatabase($this->data);
        $this->user = "john.doe@example.com";
    }

    function testUpdateAFeed() {
        // update a valid feed with both new and changed items
        Data::$db->feedUpdate(1);
        $ts = gmdate("Y-m-d H:i:s");
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id", "feed","url","title","author","published","edited","content","guid","url_title_hash","url_content_hash","title_content_hash","modified"],
            'arsse_editions' => ["id","article","modified"],
            'arsse_marks'    => ["id","article","read","starred","modified"],
        ]);
        $state['arsse_articles']['rows'][2] = [3,1,'http://example.com/3','Article title 3 (updated)','','2000-01-03 00:00:00','2000-01-03 00:00:00','<p>Article content 3</p>','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','6cc99be662ef3486fef35a890123f18d74c29a32d714802d743c5b4ef713315a','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','d5faccc13bf8267850a1e8e61f95950a0f34167df2c8c58011c0aaa6367026ac',$ts];
        $state['arsse_articles']['rows'][3] = [4,1,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:01','<p>Article content 4</p>','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$ts];
        $state['arsse_articles']['rows'][5] = [6,1,'http://example.com/6','Article title 6','','2000-01-06 00:00:00','2000-01-06 00:00:00','<p>Article content 6</p>','b3461ab8e8759eeb1d65a818c65051ec00c1dfbbb32a3c8f6999434e3e3b76ab','91d051a8e6749d014506848acd45e959af50bf876427c4f0e3a1ec0f04777b51','211d78b1a040d40d17e747a363cc283f58767b2e502630d8de9b8f1d5e941d18','5ed68ccb64243b8c1931241d2c9276274c3b1d87f223634aa7a1ab0141292ca7',$ts];
        $state['arsse_editions']['rows'] = array_merge($state['arsse_editions']['rows'], [
            [6,6,$ts],
            [7,3,$ts],
            [8,4,$ts],
        ]);
        $state['arsse_marks']['rows'][2] = [3,3,0,1,$ts];
        $state['arsse_marks']['rows'][3] = [4,4,0,0,$ts];
        $state['arsse_marks']['rows'][6] = [10,3,0,0,$ts];
        $this->compareExpectations($state);
        // update a valid feed which previously had an error
        Data::$db->feedUpdate(2);
        // update an erroneous feed which previously had no errors
        Data::$db->feedUpdate(3);
        $state = $this->primeExpectations($this->data, [
            'arsse_feeds' => ["id","err_count","err_msg"],
        ]);
        $state['arsse_feeds']['rows'][1] = [2,0,""];
        $state['arsse_feeds']['rows'][2] = [3,1,'Feed URL "http://localhost:8000/Feed/Fetching/Error?code=404" is invalid'];
        $this->compareExpectations($state);
        // update the bad feed again, twice
        Data::$db->feedUpdate(3);
        Data::$db->feedUpdate(3);
        $state['arsse_feeds']['rows'][2] = [3,3,'Feed URL "http://localhost:8000/Feed/Fetching/Error?code=404" is invalid'];
        $this->compareExpectations($state);
        // FIXME: Need to test enclosures
    }

    function testUpdateAFeedThrowingExceptions() {
        $this->assertException("invalidUrl", "Feed");
        Data::$db->feedUpdate(3, true);
    }

    function testListStaleFeeds() {
        $this->assertSame([1,3,4], Data::$db->feedListStale());
        Data::$db->feedUpdate(3);
        Data::$db->feedUpdate(4);
        $this->assertSame([1], Data::$db->feedListStale());
    }
}