<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;
use JKingWeb\Arsse\REST\Exception501;
use JKingWeb\Arsse\REST\Exception405;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
    function __construct() {
    }

    function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        // try to authenticate
        if(!Data::$user->authHTTP()) return new Response(401, "", "", ['WWW-Authenticate: Basic realm="NextCloud News API v1-2"']);
        // only accept GET, POST, PUT, or DELETE
        if(!in_array($req->method, ["GET", "POST", "PUT", "DELETE"])) return new Response(405, "", "", ['Allow: GET, POST, PUT, DELETE']);
        // normalize the input
        if($req->body) {
            // if the entity body is not JSON according to content type, return "415 Unsupported Media Type"
            if(!preg_match("<^application/json\b|^$>", $req->type)) return new Response(415, "", "", ['Accept: application/json']);
            try {
                $data = json_decode($req->body, true);
            } catch(\Throwable $e) {
                // if the body could not be parsed as JSON, return "400 Bad Request"
                return new Response(400);
            }
        } else {
            $data = [];
        }
        // FIXME: Do query parameters take precedence in NextCloud? Is there a conflict error when values differ?
        $data = array_merge($data, $req->query);
        // check to make sure the requested function is implemented
        try {
            $func = $this->chooseCall($req->paths, $req->method);
        } catch(Exception501 $e) {
            return new Response(501);
        } catch(Exception405 $e) {
            return new Response(405, "", "", ["Allow: ".$e->getMessage()]);
        }
        if(!method_exists($this, $func)) return new Response(501);
        // dispatch
        try {
            Data::$db->dateFormatDefault("unix");
            return $this->$func($req->paths, $data);
        } catch(Exception $e) {
            // if there was a REST exception return 400
            return new Response(400);
        } catch(AbstractException $e) {
            // if there was any other Arsse exception return 500
            return new Response(500);
        }
    }

    protected function chooseCall(array $url, string $method): string {
        $choices = [
            'items' => [],
            'folders' => [
                ''       => ['GET' => "folderList",   'POST'   => "folderAdd"],
                '0'      => ['PUT' => "folderRename", 'DELETE' => "folderRemove"],
                '0/read' => ['PUT' => "folderMarkRead"],
            ],
            'feeds' => [
                ''         => ['GET' => "subscriptionList", 'POST' => "subscriptionAdd"],
                '0'        => ['DELETE' => "subscriptionRemove"],
                '0/move'   => ['PUT' => "subscriptionMove"],
                '0/rename' => ['PUT' => "subscriptionRename"],
                '0/read'   => ['PUT' => "subscriptionMarkRead"],
                'all'      => ['GET' => "feedListStale"],
                'update'   => ['GET' => "feedUpdate"],
            ],
            'cleanup' => [],
            'version' => [
                '' => ['GET' => "versionReport"],
            ],
            'status' => [],
            'user' => [],
        ];
        // the first path element is the overall scope of the request
        $scope = $url[0];
        // any URL components which are only digits should be replaced with "#", for easier comparison (integer segments are IDs, and we don't care about the specific ID)
        for($a = 0; $a < sizeof($url); $a++) {
            if($this->validateId($url[$a])) $url[$a] = "0";
        }
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // if the scope is not supported, return 501
        if(!array_key_exists($scope, $choices)) throw new Exception501();
        // we now evaluate the supplied URL against every supported path for the selected scope
        // the URL is evaluated as an array so as to avoid decoded escapes turning invalid URLs into valid ones
        foreach($choices[$scope] as $path => $funcs) {
            // add the scope to the path to match against and split it
            $path = (string) $path;
            $path = (strlen($path)) ? "$scope/$path" : $scope;
            $path = explode("/", $path);
            if($path===$url) {
                // if the path matches, make sure the method is allowed
                if(array_key_exists($method,$funcs)) {
                    // if it is allowed, return the object method to run
                    return $funcs[$method];
                } else {
                    // otherwise return 405
                    throw new Exception405(implode(", ", array_keys($funcs)));
                }
            }
        }
        // if the path was not found, return 501
        throw new Exception501();
    }
    
    // list folders
    protected function folderList(array $url, array $data): Response {
        $folders = Data::$db->folderList(Data::$user->id, null, false)->getAll();
        return new Response(200, ['folders' => $folders]);
    }

    // create a folder
    protected function folderAdd(array $url, array $data): Response {
        try {
            $folder = Data::$db->folderAdd(Data::$user->id, $data);
        } catch(ExceptionInput $e) {
            switch($e->getCode()) {
                // folder already exists
                case 10236: return new Response(409);
                // folder name not acceptable
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400);
            }
        }
        $folder = Data::$db->folderPropertiesGet(Data::$user->id, $folder);
        return new Response(200, ['folders' => [$folder]]);
    }

    // delete a folder
    protected function folderRemove(array $url, array $data): Response {
        // perform the deletion
        try {
            Data::$db->folderRemove(Data::$user->id, (int) $url[1]);
        } catch(ExceptionInput $e) {
            // folder does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a folder (also supports moving nesting folders, but this is not a feature of the API)
    protected function folderRename(array $url, array $data): Response {
        // there must be some change to be made
        if(!sizeof($data)) return new Response(422);
        // perform the edit
        try {
            Data::$db->folderPropertiesSet(Data::$user->id, (int) $url[1], $data);
        } catch(ExceptionInput $e) {
            switch($e->getCode()) {
                // folder does not exist
                case 10239: return new Response(404);
                // folder already exists
                case 10236: return new Response(409);
                // folder name not acceptable
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400);
            }
        }
        return new Response(204);
    }

    // return the server version
    protected function versionReport(array $url, array $data): Response {
        return new Response(200, ['version' => \JKingWeb\Arsse\VERSION]);
    }

    protected function feedTranslate(array $feed, bool $overwrite = false): array {
        // cast values
        $feed = $this->mapFieldTypes($feed, [
            'folder' => "int",
            'pinned' => "bool",
        ]);
        // map fields to proper names
        $feed = $this->mapFieldNames($feed, [
            'source'     => "link",
            'favicon'    => "faviconLink",
            'folder'     => "folderId",
            'unread'     => "unreadCount",
            'order_type' => "ordering",
            'err_count'  => "updateErrorCount",
            'err_msg'    => "lastUpdateError",
        ], $overwrite);
        return $feed;
    }
    
    // return list of feeds for the logged-in user
    protected function subscriptionList(array $url, array $data): Response {
        $subs = Data::$db->subscriptionList(Data::$user->id);
        $out = [];
        foreach($subs as $sub) {
            $sub = $this->feedTranslate($sub);
            $out[] = $sub;
        }
        $out = ['feeds' => $out];
        $out['starredCount'] = Data::$db->articleStarredCount(Data::$user->id);
        $newest = Data::$db->editionLatest(Data::$user->id);
        if($newest) $out['newestItemId'] = $newest;
        return new Response(200, $out);
    }
    
    // return list of feeds which should be refreshed
    protected function feedListStale(array $url, array $data): Response {
        // function requires admin rights per spec
        if(Data::$user->rightsGet(Data::$user->id)==User::RIGHTS_NONE) return new Response(403);
        // list stale feeds which should be checked for updates
        $feeds = Data::$db->feedListStale();
        $out = [];
        foreach($feeds as $feed) {
            // since in our implementation feeds don't belong the users, the 'userId' field will always be an empty string
            $out[] = ['id' => $feed, 'userId' => ""];
        }
        return new Response(200, ['feeds' => $out]);
    }
    
    // refresh a feed
    protected function feedUpdate(array $url, array $data): Response {
        // perform an update of a single feed
        if(!array_key_exists("feedId", $data)) return new Response(422);
        if(!$this->validateId($data['feedId'])) return new Response(404);
        try {
            Data::$db->feedUpdate((int) $data['feedId']);
        } catch(ExceptionInput $e) {
            return new Response(404);
        }
        return new Response(200);
    }

    // add a new feed
    protected function subscriptionAdd(array $url, array $data): Response {
        // normalize the feed URL
        if(!array_key_exists("url", $data)) {
            $url = "";
        } else {
            $url = $data['url'];
        }
        // normalize the folder ID, if specified
        if(!array_key_exists("folderId", $data)) {
            $folder = null;
        } else {
            $folder = $data['folderId'];
            $folder = $folder ? $folder : null;
        }
        // try to add the feed
        $tr = Data::$db->begin();
        try {
            $id = Data::$db->subscriptionAdd(Data::$user->id, $url);
        } catch(ExceptionInput $e) {
            // feed already exists
            return new Response(409);
        } catch(FeedException $e) {
            // feed could not be retrieved
            return new Response(422);
        }
        // if a folder was specified, move the feed to the correct folder; silently ignore errors
        if($folder) {
            try {
                Data::$db->subscriptionPropertiesSet(Data::$user->id, $id, ['folder' => $folder]);
            } catch(ExceptionInput $e) {}
        }
        $tr->commit();
        // fetch the feed's metadata and format it appropriately
        $feed = Data::$db->subscriptionPropertiesGet(Data::$user->id, $id);
        $feed = $this->feedTranslate($feed);
        $out = ['feeds' => [$feed]];
        $newest = Data::$db->editionLatest(Data::$user->id, ['subscription' => $id]);
        if($newest) $out['newestItemId'] = $newest;
        return new Response(200, $out);
    }

    // delete a feed
    protected function subscriptionRemove(array $url, array $data): Response {
        try {
            Data::$db->subscriptionRemove(Data::$user->id, (int) $url[1]);
        } catch(ExceptionInput $e) {
            // feed does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a feed
    protected function subscriptionRename(array $url, array $data): Response {
        // normalize input
        $in = [];
        if(array_key_exists("feedTitle", $data)) {
            $in['title'] = $data['feedTitle'];
        } else {
            return new Response(422);
        }
        // perform the renaming
        try {
            Data::$db->subscriptionPropertiesSet(Data::$user->id, (int) $url[1], $in);
        } catch(ExceptionInput $e) {
            switch($e->getCode()) {
                // subscription does not exist
                case 10239: return new Response(404);
                // name is invalid
                case 10231:
                case 10232: return new Response(422);
                // other errors related to input
                default: return new Response(400);
            }
        }
        return new Response(204);
    }

    // move a feed to a folder
    protected function subscriptionMove(array $url, array $data): Response {
        // normalize input for move and rename
        $in = [];
        if(array_key_exists("folderId", $data)) {
            $folder = $data['folderId'];
            if(!$this->validateId($folder)) return new Response(422);
            if(!$folder) $folder = null;
            $in['folder'] = $folder;
        } else {
            return new Response(422);
        }
        // perform the move
        try {
            Data::$db->subscriptionPropertiesSet(Data::$user->id, (int) $url[1], $in);
        } catch(ExceptionInput $e) {
            switch($e->getCode()) {
                // subscription does not exist
                case 10239: return new Response(404);
                // folder does not exist
                case 10235: return new Response(422);
                // other errors related to input
                default: return new Response(400);
            }
        }
        return new Response(204);
    }
}