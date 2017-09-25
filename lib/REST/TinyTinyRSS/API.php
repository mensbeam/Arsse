<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;

class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    const LEVEL = 14;
    const VERSION = "17.4";
    const OVVERIDE = [
        'auth' => ["login"],
    ];
    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if ($req->method != "POST") {
            // only POST requests are allowed
            return new Response(405, "", "", ["Allow: POST"]);
        }
        if ($req->body) {
            // only JSON entities are allowed
            if (!preg_match("<^application/json\b|^$>", $req->type)) {
                return new Response(415, "", "", ['Accept: application/json']);
            }
            $data = @json_decode($req->body, true);
            if (json_last_error() != \JSON_ERROR_NONE || !is_array($data)) {
                // non-JSON input indicates an error
                return new Response(400);
            }
            // layer input over defaults
            $data = array_merge([
                'seq' => 0,
                'op'  => "",
                'sid' => null,
            ], $data);
            try {
                if (!in_array($data['op'], self::OVERRIDE['auth'])) {
                    // unless otherwise specified, a session identifier is required
                    $this->resumeSession($data['sid']);
                }
                $method = "op".ucfirst($data['op']);
                if (!method_exists($this, $method)) {
                    // because method names are supposed to be case insensitive, we need to try a bit harder to match
                    $method = strtolower($method);
                    $map = get_class_methods($this);
                    $map = array_combine(array_map("strtolower", $map), $map);
                    if(!array_key_exists($method, $map)) {
                        // if the method really doesn't exist, throw an exception
                        throw new Exception("UNKNWON_METHOD", ['method' => $data['op']]);
                    }
                    // otherwise retrieve the correct camelCase and continue
                    $method = $map[$method];
                }
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 0,
                    'content' => $this->$method($data),
                ]);
            } catch (Exception $e) {
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 1,
                    'content' => $e->getData(),
                ]);
            } catch (AbstractException $e) {
                return new Response(500);
            }
        } else {
            // absence of a request body indicates an error
            return new Response(400);
        }
    }

    protected function resumeSession($id): bool {
        try {
            // verify the supplied session is valid
            $s = Arsse::$db->sessionResume((string) $id);
        } catch (\JKingWeb\Arsse\User\ExceptionSession $e) {
            // if not throw an exception
            throw new Exception("NOT_LOGGED_IN");
        }
        // resume the session (currently only the user name)
        Arsse::$user->id = $s['user'];
        return true;
    }

    public function opGetApiLevel(array $data): aray {
        return ['level' => self::LEVEL];
    }
    
    public function opGetVersion(array $data): array {
        return [
            'version'       => self::VERSION,
            'arsse_version' => \JKingWeb\Arsse\VERSION,
        ];
    }

    public function opLogin(array $data): aray {
        if (isset($data['user']) && isset($data['password']) && Arsse::$user->auth($data['user'], $data['password'])) {
            $id = Arsse::$db->sessionCreate($data['user']);
            return [
                'session_id' => $id, 
                'api_level'  => self::LEVEL
            ];
        } else {
            throw new Exception("LOGIN_ERROR");
        }
    }

    public function opLogout(array $data): array {
        Arsse::$db->sessionDestroy(Arsse::$user->id, $data['sid']);
        return ['status' => "OK"];
    }

    public function opIsLoggedIn(array $data): array {
        // session validity is already checked by the dispatcher, so we need only return true
        return ['status' => true];
    }
}