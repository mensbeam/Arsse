<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextCloudNews;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
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
            // check to make sure the requested function is implemented
            $func = $scope.$req->method;
            if(!method_exists($this, $func)) return new Response(501);
            // dispatch
            try {
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
        if(strval(intval($url[0])) !== $url[0]) return new Response(404);
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
        if(strval(intval($url[0])) !== $url[0]) return new Response(404);
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

    protected function versionGET(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(200, ['version' => \JKingWeb\Arsse\VERSION]);
    }

    protected function versionPOST(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
    }

    protected function versionPUT(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
    }

    protected function versionDELETE(array $url, array $data): Response {
        // if URL is more than '/version' this is an error
        if(sizeof($url)) return new Response(404);
        return new Response(405, "", "", ['Allow: GET']);
    }

}