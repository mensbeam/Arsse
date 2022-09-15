<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesIcon {
    protected function setUpSeriesIcon(): void {
        // set up the test data
        $past = gmdate("Y-m-d H:i:s", strtotime("now - 1 minute"));
        $future = gmdate("Y-m-d H:i:s", strtotime("now + 1 minute"));
        $now = gmdate("Y-m-d H:i:s", strtotime("now"));
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_icons' => [
                'columns' => ["id", "url", "type", "data"],
                'rows'    => [
                    [1,'http://localhost:8000/Icon/PNG','image/png',base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
                    [2,'http://localhost:8000/Icon/GIF','image/gif',base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
                    [3,'http://localhost:8000/Icon/SVG1','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>'],
                    [4,'http://localhost:8000/Icon/SVG2','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>'],
                ],
            ],
            'arsse_feeds' => [
                'columns' => ["id", "url", "title", "err_count", "err_msg", "modified", "next_fetch", "size", "icon"],
                'rows'    => [
                    [1,"http://localhost:8000/Feed/Matching/3","Ook",0,"",$past,$past,0,1],
                    [2,"http://localhost:8000/Feed/Matching/1","Eek",5,"There was an error last time",$past,$future,0,2],
                    [3,"http://localhost:8000/Feed/Fetching/Error?code=404","Ack",0,"",$past,$now,0,3],
                    [4,"http://localhost:8000/Feed/NextFetch/NotModified?t=".time(),"Ooook",0,"",$past,$past,0,null],
                    [5,"http://localhost:8000/Feed/Parsing/Valid","Ooook",0,"",$past,$future,0,2],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "feed"],
                'rows'    => [
                    [1,'john.doe@example.com',1],
                    [2,'john.doe@example.com',2],
                    [3,'john.doe@example.com',3],
                    [4,'john.doe@example.com',4],
                    [5,'john.doe@example.com',5],
                    [6,'jane.doe@example.com',5],
                ],
            ],
        ];
    }

    protected function tearDownSeriesIcon(): void {
        unset($this->data);
    }

    public function testListTheIconsOfAUser() {
        $exp = [
            ['id' => 1,'url' => 'http://localhost:8000/Icon/PNG',  'type' => 'image/png',     'data' => base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
            ['id' => 2,'url' => 'http://localhost:8000/Icon/GIF',  'type' => 'image/gif',     'data' => base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
            ['id' => 3,'url' => 'http://localhost:8000/Icon/SVG1', 'type' => 'image/svg+xml', 'data' => '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>'],
        ];
        $this->assertResult($exp, Arsse::$db->iconList("john.doe@example.com"));
        $exp = [
            ['id' => 2,'url' => 'http://localhost:8000/Icon/GIF',  'type' => 'image/gif',     'data' => base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
        ];
        $this->assertResult($exp, Arsse::$db->iconList("jane.doe@example.com"));
    }
}
