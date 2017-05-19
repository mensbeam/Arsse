<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;

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
        // match the path
        if(preg_match("<^/(items|folders|feeds|cleanup|version|status|user)(?:/([^/]+))?(?:/([^/]+))?(?:/([^/]+))?/?$>", $req->path, $url)) {
            // clean up the path
            $scope = $url[1];
            unset($url[0]);
            unset($url[1]);
            $url = array_filter($url);
            $url = array_values($url);
            // decode any % sequences in the URL
            $url = array_map(function($v){return rawurldecode($v);}, $url);
            // check to make sure the requested function is implemented
            $func = $scope.$req->method;
            if(!method_exists($this, $func)) return new Response(501);
            // dispatch
            try {
                Data::$db->dateFormatDefault("unix");
                return $this->$func($url, $data);
            } catch(Exception $e) {
                // if there was a REST exception return 400
                return new Response(400);
            } catch(AbstractException $e) {
                // if there was any other Arsse exception return 500
                return new Response(500);
            }
        } else {
            return new Response(404);
        }
    }
    
    // list folders
    protected function foldersGET(array $url, array $data): Response {
        // if URL is more than '/folders' this is an error
        if(sizeof($url)==1)  return new Response(405, "", "", ['Allow: PUT, DELETE']);
        if(sizeof($url) > 1) return new Response(404);
        $folders = Data::$db->folderList(Data::$user->id, null, false)->getAll();
        return new Response(200, ['folders' => $folders]);
    }

    // create a folder
    protected function foldersPOST(array $url, array $data): Response {
        // if URL is more than '/folders' this is an error
        if(sizeof($url)==1)  return new Response(405, "", "", ['Allow: PUT, DELETE']);
        if(sizeof($url) > 1) return new Response(404);
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
    protected function foldersDELETE(array $url, array $data): Response {
        // if URL is more or less than '/folders/$id' this is an error
        if(sizeof($url) < 1) return new Response(405, "", "", ['Allow: GET, POST']);
        if(sizeof($url) > 1) return new Response(404);
        // folder ID must be integer
        if(!$this->validateId($url[0])) return new Response(404);
        // perform the deletion
        try {
            Data::$db->folderRemove(Data::$user->id, (int) $url[0]);
        } catch(ExceptionInput $e) {
            // folder does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a folder (also supports moving nesting folders, but this is not a feature of the API)
    protected function foldersPUT(array $url, array $data): Response {
        // if URL is more or less than '/folders/$id' this is an error
        if(sizeof($url) < 1) return new Response(405, "", "", ['Allow: GET, POST']);
        if(sizeof($url) > 1) return new Response(404);
        // folder ID must be integer
        if(!$this->validateId($url[0])) return new Response(404);
        // there must be some change to be made
        if(!sizeof($data)) return new Response(422);
        // perform the edit
        try {
            Data::$db->folderPropertiesSet(Data::$user->id, (int) $url[0], $data);
        } catch(ExceptionInput $e) {
            switch($e->getCode()) {
                // folder does not exist
                case 10235: return new Response(404);
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
    protected function versionGET(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(200, ['version' => \JKingWeb\Arsse\VERSION]);
    }

    // invalid function
    protected function versionPOST(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
    }

    // invalid function
    protected function versionPUT(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
    }

    // invalid function
    protected function versionDELETE(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
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
    // return list of feeds which should be refreshed
    // refresh a feed
    protected function feedsGET(array $url, array $data): Response {
        // URL may be /feeds/[all|update] only
        $args = sizeof($url);
        if($args==2 && in_array($url[1], ["rename","move","read"])) return new Response(405, "", "", ['Allow: PUT, DELETE']);
        if($args > 1)                                               return new Response(404);
        if($args==1 && !in_array($url[0], ["all","update"]))        return new Response(405, "", "", ['Allow: PUT, DELETE']);
        // valid action are listing owned subscriptions or (for admins) listing stale feeds or updating a feed
        if($args==1) {
            // listing stale feeds for updating and updating itself require admin rights per spec
            if(Data::$user->rightsGet(Data::$user->id)==User::RIGHTS_NONE) return new Response(403);
            if($url[0]=="all") {
                // list stale feeds which should be checked for updates
                $feeds = Data::$db->feedListStale();
                $out = [];
                foreach($feeds as $feed) {
                    // since in our implementation feeds don't belong the users, the 'userId' field will always be an empty string
                    $out[] = ['id' => $feed, 'userId' => ""];
                }
                return new Response(200, ['feeds' => $out]);
            } elseif($url[0]=="update") {
                // perform an update of a single feed
                if(!array_key_exists("feedId", $data) || $data['feedId'] < 1) return new Response(422);
                try {
                    Data::$db->feedUpdate($data['feedId']);
                } catch(ExceptionInput $e) {
                    return new Response(404);
                }
                return new Response(200);
            }
        } else { 
            // list subscriptions for the logged-in user
            $subs = Data::$db->subscriptionList(Data::$user->id);
            $out = [];
            foreach($subs as $sub) {
                $sub = $this->feedTranslate($sub);
                $out[] = $sub;
            }
            $out = ['feeds' => $out];
            $out['starredCount'] = Data::$db->articleStarredCount(Data::$user->id);
            $newest = Data::$db->editionLatest(Data::$user->id, ['subscription' => $id]);
            if($newest) $out['newestItemId'] = $newest;
            return new Response(200, $out);
        }
    }

    // add a new feed
    protected function feedsPOST(array $url, array $data): Response {
        // if URL is more than '/feeds' this is an error
        $args = sizeof($url);
        if($args==1 && in_array($url[0], ["all","update"]))         return new Response(405, "", "", ['Allow: GET']);
        if($args==1)                                                return new Response(405, "", "", ['Allow: PUT, DELETE']);
        if($args==2 && in_array($url[1], ["rename","move","read"])) return new Response(405, "", "", ['Allow: PUT']);
        if($args)                                                   return new Response(404);
        // normalize the URL
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
            $folder = $folder ? null : $folder;
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
    protected function feedsDELETE(array $url, array $data): Response {
        // if URL is more or less than '/feeds/$id' this is an error
        if(sizeof($url) < 1) return new Response(405, "", "", ['Allow: GET, POST']);
        if(sizeof($url) > 1) return new Response(404);
        // folder ID must be integer
        if(!$this->validateId($url[0])) return new Response(404);
        // perform the deletion
        try {
            Data::$db->subscriptionRemove(Data::$user->id, (int) $url[0]);
        } catch(ExceptionInput $e) {
            // feed does not exist
            return new Response(404);
        }
        return new Response(204);
    }

    // rename a feed
    // move a feed to a folder
    // mark items from a feed as read
    protected function feedsPUT(array $url, array $data): Response {
        // URL may be /feeds/<id>/[rename|move|read]
        $args = sizeof($url);
        if(!$args)                                                   return new Response(405, "", "", ['Allow: GET, POST']);
        if($args > 2)                                                return new Response(404);
        if(in_array($url[0], ["all", "update"])) {
            if($args==1)                                             return new Response(405, "", "", ['Allow: GET']);
                                                                     return new Response(404);
        }
        if($args==2 && !in_array($url[1], ["rename","move","read"])) return new Response(404);
        // if the feed ID is not an integer, this is also an error
        if(!$this->validateId($url[0])) return new Response(404);
        // normalize input for move and rename
        $in = [];
        if(array_key_exists("feedTitle", $data)) {
            $in['title'] = $data['feedTitle'];
        }
        if(array_key_exists("folderId", $data)) {
            $folder = $data['folderId'];
            if(!$this->validateId($folder)) return new Response(422);
            if(!$folder) $folder = null;
            $in['folder'] = $folder;
        }
        // perform the move and/or rename
        if($in) {
            try {
                Data::$db->subscriptionPropertiesSet(Data::$user->id, (int) $url[0], $in);
            } catch(ExceptionInput $e) {
                return new Response(404);
            }
        }
        // mark items as read, if requested
        if(array_key_exists("newestItemId", $data)) {
            $newest = $data['newestItemId'];
            if(!$this->validateId($newest)) return new Response(422);
            // FIXME: do the marking as read
        }
        return new Response(204);
    }
}