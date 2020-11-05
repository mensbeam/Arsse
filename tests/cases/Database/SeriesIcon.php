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
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                    'num'      => 'int',
                ],
                'rows' => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_icons' => [
                'columns' => [
                    'id'   => "int",
                    'url'  => "str",
                    'type' => "str",
                    'data' => "blob",
                ],
                'rows' => [
                    [1,'http://localhost:8000/Icon/PNG','image/png',base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
                    [2,'http://localhost:8000/Icon/GIF','image/gif',base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
                    [3,'http://localhost:8000/Icon/SVG1','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>'],
                    [4,'http://localhost:8000/Icon/SVG2','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>'],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                    'err_count'  => "int",
                    'err_msg'    => "str",
                    'modified'   => "datetime",
                    'next_fetch' => "datetime",
                    'size'       => "int",
                    'icon'       => "int",
                ],
                'rows' => [
                    [1,"http://localhost:8000/Feed/Matching/3","Ook",0,"",$past,$past,0,null],
                    [2,"http://localhost:8000/Feed/Matching/1","Eek",5,"There was an error last time",$past,$future,0,null],
                    [3,"http://localhost:8000/Feed/Fetching/Error?code=404","Ack",0,"",$past,$now,0,null],
                    [4,"http://localhost:8000/Feed/NextFetch/NotModified?t=".time(),"Ooook",0,"",$past,$past,0,null],
                    [5,"http://localhost:8000/Feed/Parsing/Valid","Ooook",0,"",$past,$future,0,null],
                    // these feeds all test icon caching
                    [6,"http://localhost:8000/Feed/WithIcon/PNG",null,0,"",$past,$future,0,1], // no change when updated
                    [7,"http://localhost:8000/Feed/WithIcon/GIF",null,0,"",$past,$future,0,1], // icon ID 2 will be assigned to feed when updated
                    [8,"http://localhost:8000/Feed/WithIcon/SVG1",null,0,"",$past,$future,0,3], // icon ID 3 will be modified when updated
                    [9,"http://localhost:8000/Feed/WithIcon/SVG2",null,0,"",$past,$future,0,null], // icon ID 4 will be created and assigned to feed when updated
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'    => "int",
                    'owner' => "str",
                    'feed'  => "int",
                ],
                'rows' => [
                    [1,'john.doe@example.com',1],
                    [2,'john.doe@example.com',2],
                    [3,'john.doe@example.com',3],
                    [4,'john.doe@example.com',4],
                    [5,'john.doe@example.com',5],
                    [6,'jane.doe@example.com',1],
                ],
            ],
        ];
    }

    protected function tearDownSeriesIcon(): void {
        unset($this->data);
    }
}
