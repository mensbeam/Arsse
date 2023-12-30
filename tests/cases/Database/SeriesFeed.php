<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Test\Result;

trait SeriesFeed {
    protected static $drv;
    protected $matches;

    protected function setUpSeriesFeed(): void {
        // set up the test data
        $past = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $future = gmdate("Y-m-d H:i:s", strtotime("now + 1 minute"));
        $now = gmdate("Y-m-d H:i:s", strtotime("now"));
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "", 1],
                    ["john.doe@example.com", "", 2],
                    ["jane.doe@example.org", "", 3],
                ],
            ],
            'arsse_icons' => [
                'columns' => ["id", "url", "type", "data"],
                'rows'    => [
                    [1,'http://localhost:8000/Icon/PNG','image/png',base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
                    [2,'http://localhost:8000/Icon/GIF','image/gif',base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
                    // this actually contains the data of SVG2, which will lead to a row update when retieved
                    [3,'http://localhost:8000/Icon/SVG1','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>'],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "keep_rule", "block_rule", "url", "feed_title", "err_count", "err_msg", "last_mod", "next_fetch", "size", "icon"],
                'rows'    => [
                    [1, 'john.doe@example.com',null,         '^Sport$',"http://localhost:8000/Feed/Matching/3",                     "Ook",  0,"",                            $past,$past,  0,null],
                    [2, 'john.doe@example.com',"",           null,     "http://localhost:8000/Feed/Matching/1",                     "Eek",  5,"There was an error last time",$past,$future,0,null],
                    [3, 'john.doe@example.com','\w+',        null,     "http://localhost:8000/Feed/Fetching/Error?code=404",        "Ack",  0,"",                            $past,$now,   0,null],
                    [4, 'john.doe@example.com','\w+',        "[",      "http://localhost:8000/Feed/NextFetch/NotModified?t=".time(),"Ooook",0,"",                            $past,$past,  0,null], // invalid rule leads to both rules being ignored
                    [5, 'john.doe@example.com',null,         'and/or', "http://localhost:8000/Feed/Parsing/Valid",                  "Ooook",0,"",                            $past,$future,0,null],
                    [6, 'jane.doe@example.com','^(?i)[a-z]+','3|6',    "http://localhost:8000/Feed/Matching/3",                     "Ook",  0,"",                            $past,$past,  0,null],
                    // these feeds all test icon caching
                    [16,'jane.doe@example.org',null,         null,     "http://localhost:8000/Feed/WithIcon/PNG",                   null,   0,"",                            $past,$future,0,1], // no change when updated
                    [17,'jane.doe@example.org',null,         null,     "http://localhost:8000/Feed/WithIcon/GIF",                   null,   0,"",                            $past,$future,0,1], // icon ID 2 will be assigned to feed when updated
                    [18,'jane.doe@example.org',null,         null,     "http://localhost:8000/Feed/WithIcon/SVG1",                  null,   0,"",                            $past,$future,0,3], // icon ID 3 will be modified when updated
                    [19,'jane.doe@example.org',null,         null,     "http://localhost:8000/Feed/WithIcon/SVG2",                  null,   0,"",                            $past,$future,0,null], // icon ID 4 will be created and assigned to feed when updated
                ],
            ],
            'arsse_articles' => [
                'columns' => ["id", "subscription", "url", "title", "author", "published", "edited", "guid", "url_title_hash", "url_content_hash", "title_content_hash", "modified", "read", "starred", "hidden", "marked"],
                'rows'    => [
                    [1, 1,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:00','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',$past,1,0,0,null],
                    [2, 1,'http://example.com/2','Article title 2','','2000-01-02 00:00:00','2000-01-02 00:00:00','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',$past,0,0,0,null],
                    [3, 1,'http://example.com/3','Article title 3','','2000-01-03 00:00:00','2000-01-03 00:00:00','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',$past,1,0,0,null],
                    [4, 1,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:00','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$past,0,1,0,null],
                    [5, 1,'http://example.com/5','Article title 5','','2000-01-05 00:00:00','2000-01-05 00:00:00','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',$past,0,0,0,null],
                    [6, 2,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:00','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',$past,0,0,0,null],
                    [7, 5,'',                    '',               '','2000-01-01 00:00:00','2000-01-01 00:00:00','205e986f4f8b3acfa281227beadb14f5e8c32c8dae4737f888c94c0df49c56f8','',                                                                '',                                                                '',                                                                $past,0,0,0,null],
                    [11,6,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:00','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',$past,1,0,0,$past],
                    [12,6,'http://example.com/2','Article title 2','','2000-01-02 00:00:00','2000-01-02 00:00:00','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',$past,1,0,0,$past],
                    [13,6,'http://example.com/3','Article title 3','','2000-01-03 00:00:00','2000-01-03 00:00:00','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',$past,1,1,0,$past],
                    [14,6,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:00','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$past,1,0,1,$past],
                    [15,6,'http://example.com/5','Article title 5','','2000-01-05 00:00:00','2000-01-05 00:00:00','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',$past,1,1,0,$past],
                ],
            ],
            'arsse_article_contents' => [
                'columns' => ["id", "content"],
                'rows'    => [
                    [1, '<p>Article content 1</p>'],
                    [2, '<p>Article content 2</p>'],
                    [3, '<p>Article content 3</p>'],
                    [4, '<p>Article content 4</p>'],
                    [5, '<p>Article content 5</p>'],
                    [6, '<p>Article content 1</p>'],
                    [7, ''],
                    [11,'<p>Article content 1</p>'],
                    [12,'<p>Article content 2</p>'],
                    [13,'<p>Article content 3</p>'],
                    [14,'<p>Article content 4</p>'],
                    [15,'<p>Article content 5</p>'],
                ],
            ],
            'arsse_editions' => [
                'columns' => ["id", "article", "modified"],
                'rows'    => [
                    [1,1,$past],
                    [2,2,$past],
                    [3,3,$past],
                    [4,4,$past],
                    [5,5,$past],
                ],
            ],
            'arsse_enclosures' => [
                'columns' => ["article", "url", "type"],
                'rows'    => [
                    [7,'http://example.com/png','image/png'],
                ],
            ],
            'arsse_categories' => [
                'columns' => ["article", "name"],
                'rows'    => [
                    [7,'Syrinx'],
                ],
            ],
        ];
        $this->matches = [
            [
                'id'                 => 4,
                'edited'             => '2000-01-04 00:00:00',
                'guid'               => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'url_title_hash'     => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8',
                'url_content_hash'   => 'f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3',
                'title_content_hash' => 'ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
            ],
            [
                'id'                 => 5,
                'edited'             => '2000-01-05 00:00:00',
                'guid'               => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'url_title_hash'     => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022',
                'url_content_hash'   => '834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900',
                'title_content_hash' => '43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
            ],
        ];
    }

    protected function tearDownSeriesFeed(): void {
        unset($this->data, $this->matches);
    }

    public function testListLatestItems(): void {
        $this->assertResult($this->matches, Arsse::$db->feedMatchLatest(1, 2));
    }

    public function testMatchItemsById(): void {
        $this->assertResult($this->matches, Arsse::$db->feedMatchIds(1, ['804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41']));
        foreach ($this->matches as $m) {
            $exp = [$m];
            $this->assertResult($exp, Arsse::$db->feedMatchIds(1, [], [$m['url_title_hash']]));
            $this->assertResult($exp, Arsse::$db->feedMatchIds(1, [], [], [$m['url_content_hash']]));
            $this->assertResult($exp, Arsse::$db->feedMatchIds(1, [], [], [], [$m['title_content_hash']]));
        }
        $this->assertResult([['id' => 1]], Arsse::$db->feedMatchIds(1, ['e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda'])); // this ID appears in both feed 1 and feed 2; only one result should be returned
    }

    public function testUpdateAFeed(): void {
        // update a valid feed with both new and changed items
        Arsse::$db->subscriptionUpdate(null, 1);
        Arsse::$db->subscriptionUpdate(null, 6);
        $now = gmdate("Y-m-d H:i:s");
        $past = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $state = $this->primeExpectations($this->data, [
            'arsse_articles'         => ["id", "subscription","url","title","author","published","edited","guid","url_title_hash","url_content_hash","title_content_hash","modified"],
            'arsse_article_contents' => ["id", "content"],
            'arsse_editions'         => ["id","article","modified"],
            'arsse_subscriptions'    => ["id","size"],
        ]);
        $state['arsse_articles']['rows'][2] = [3,1,'http://example.com/3','Article title 3 (updated)','','2000-01-03 00:00:00','2000-01-03 00:00:00','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','6cc99be662ef3486fef35a890123f18d74c29a32d714802d743c5b4ef713315a','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','d5faccc13bf8267850a1e8e61f95950a0f34167df2c8c58011c0aaa6367026ac',$now];
        $state['arsse_articles']['rows'][3] = [4,1,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:01','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$now];
        $state['arsse_articles']['rows'][] = [16,1,'http://example.com/6','Article title 6','','2000-01-06 00:00:00','2000-01-06 00:00:00','b3461ab8e8759eeb1d65a818c65051ec00c1dfbbb32a3c8f6999434e3e3b76ab','91d051a8e6749d014506848acd45e959af50bf876427c4f0e3a1ec0f04777b51','211d78b1a040d40d17e747a363cc283f58767b2e502630d8de9b8f1d5e941d18','5ed68ccb64243b8c1931241d2c9276274c3b1d87f223634aa7a1ab0141292ca7',$now];
        $state['arsse_articles']['rows'][9] = [13,6,'http://example.com/3','Article title 3 (updated)','','2000-01-03 00:00:00','2000-01-03 00:00:00','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','6cc99be662ef3486fef35a890123f18d74c29a32d714802d743c5b4ef713315a','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','d5faccc13bf8267850a1e8e61f95950a0f34167df2c8c58011c0aaa6367026ac',$now];
        $state['arsse_articles']['rows'][10] = [14,6,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:01','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',$now];
        $state['arsse_articles']['rows'][] = [17,6,'http://example.com/6','Article title 6','','2000-01-06 00:00:00','2000-01-06 00:00:00','b3461ab8e8759eeb1d65a818c65051ec00c1dfbbb32a3c8f6999434e3e3b76ab','91d051a8e6749d014506848acd45e959af50bf876427c4f0e3a1ec0f04777b51','211d78b1a040d40d17e747a363cc283f58767b2e502630d8de9b8f1d5e941d18','5ed68ccb64243b8c1931241d2c9276274c3b1d87f223634aa7a1ab0141292ca7',$now];
        $state['arsse_editions']['rows'] = array_merge($state['arsse_editions']['rows'], [
            [6, 16,$now],
            [7, 3, $now],
            [8, 4, $now],
            [9, 17,$now],
            [10,13,$now],
            [11,14,$now],

        ]);
        $state['arsse_article_contents']['rows'][2] = [3,'<p>Article content 3</p>'];
        $state['arsse_article_contents']['rows'][3] = [4,'<p>Article content 4</p>'];
        $state['arsse_article_contents']['rows'][] = [16,'<p>Article content 6</p>'];
        $state['arsse_article_contents']['rows'][9] = [13,'<p>Article content 3</p>'];
        $state['arsse_article_contents']['rows'][10] = [14,'<p>Article content 4</p>'];
        $state['arsse_article_contents']['rows'][] = [17,'<p>Article content 6</p>'];
        $state['arsse_subscriptions']['rows'][0] = [1,6];
        $state['arsse_subscriptions']['rows'][5] = [6,6];
        $this->compareExpectations(static::$drv, $state);
        $state = $this->primeExpectations($this->data, [
            'arsse_articles' => ["id", "read", "starred", "hidden", "marked"],
        ]);
        $state['arsse_articles']['rows'][2] = [3,0,0,0,null];
        $state['arsse_articles']['rows'][9] = [13,0,1,1,$past];
        $state['arsse_articles']['rows'][10] = [14,0,0,0,$past];
        $state['arsse_articles']['rows'][] = [16,0,0,0,null];
        $state['arsse_articles']['rows'][] = [17,0,0,1,null];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testUpdateAFeedWithErrors(): void {
        // update a valid feed which previously had an error
        Arsse::$db->subscriptionUpdate(null, 2);
        // update an erroneous feed which previously had no errors
        Arsse::$db->subscriptionUpdate(null, 3);
        $state = $this->primeExpectations($this->data, [
            'arsse_subscriptions' => ["id","err_count","err_msg"],
        ]);
        $state['arsse_subscriptions']['rows'][1] = [2,0,""];
        $state['arsse_subscriptions']['rows'][2] = [3,1,'Feed URL "http://localhost:8000/Feed/Fetching/Error?code=404" is invalid'];
        $this->compareExpectations(static::$drv, $state);
        // update the bad feed again, twice
        Arsse::$db->subscriptionUpdate(null, 3);
        Arsse::$db->subscriptionUpdate(null, 3);
        $state['arsse_subscriptions']['rows'][2] = [3,3,'Feed URL "http://localhost:8000/Feed/Fetching/Error?code=404" is invalid'];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testUpdateAMissingFeed(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->subscriptionUpdate(null, 2112);
    }

    public function testUpdateAnInvalidFeed(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->subscriptionUpdate(null, -1);
    }

    public function testUpdateAFeedThrowingExceptions(): void {
        $this->assertException("invalidUrl", "Feed");
        Arsse::$db->subscriptionUpdate(null, 3, true);
    }

    public function testUpdateAFeedWithEnclosuresAndCategories(): void {
        Arsse::$db->subscriptionUpdate(null, 5);
        $state = $this->primeExpectations($this->data, [
            'arsse_enclosures' => ["url","type"],
            'arsse_categories' => ["name"],
        ]);
        $state['arsse_enclosures']['rows'] = [
            ['http://example.com/svg','image/svg'],
            ['http://example.com/text','text/plain'],
        ];
        $state['arsse_categories']['rows'] = [
            ["HLN"],
            ["Aniki!"],
            ["Beams"],
            ["Bodybuilders"],
            ["Men"],
        ];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testListStaleFeeds(): void {
        $this->assertEquals([1,3,4, 6], Arsse::$db->feedListStale());
        Arsse::$db->subscriptionUpdate(null, 3);
        Arsse::$db->subscriptionUpdate(null, 4);
        $this->assertEquals([1, 6], Arsse::$db->feedListStale());
    }

    public function testCheckIconDuringFeedUpdate(): void {
        Arsse::$db->subscriptionUpdate(null, 16);
        $state = $this->primeExpectations($this->data, [
            'arsse_icons'         => ["id","url","type","data"],
            'arsse_subscriptions' => ["id", "icon"],
        ]);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAssignIconDuringFeedUpdate(): void {
        Arsse::$db->subscriptionUpdate(null, 17);
        $state = $this->primeExpectations($this->data, [
            'arsse_icons'         => ["id","url","type","data"],
            'arsse_subscriptions' => ["id", "icon"],
        ]);
        $state['arsse_subscriptions']['rows'][7][1] = 2;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testChangeIconDuringFeedUpdate(): void {
        Arsse::$db->subscriptionUpdate(null, 18);
        $state = $this->primeExpectations($this->data, [
            'arsse_icons'         => ["id","url","type","data"],
            'arsse_subscriptions' => ["id", "icon"],
        ]);
        $state['arsse_icons']['rows'][2][3] = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>';
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddIconDuringFeedUpdate(): void {
        Arsse::$db->subscriptionUpdate(null, 19);
        $state = $this->primeExpectations($this->data, [
            'arsse_icons'         => ["id","url","type","data"],
            'arsse_subscriptions' => ["id", "icon"],
        ]);
        $state['arsse_subscriptions']['rows'][9][1] = 4;
        $state['arsse_icons']['rows'][] = [4,'http://localhost:8000/Icon/SVG2','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>'];
        $this->compareExpectations(static::$drv, $state);
    }
}
