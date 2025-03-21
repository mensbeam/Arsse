<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\NextcloudNews;

use MensBeam\Mime\MimeType;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\User\ExceptionConflict;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class OCS extends \JKingWeb\Arsse\REST\AbstractHandler {
    protected const TYPES = [
        "application/json",
        "text/json",
        "application/xml",
        "text/xml",
    ];
    protected const BASE_META = [
        200 => [
            'status'     => "ok",
            'statuscode' => 200,
            'message'    => "OK"
        ],
        403 => [
            'status'     => "failure",
            'statuscode' => 998,
            'message'    => ""
        ],
        404 => [
            'status'     => "failure",
            'statuscode' => 404,
            'message'    => "User does not exist"
        ],
    ];
    // This is current as of Nextcloud 31
    protected const BASE_DATA = [
        'enabled' => true,
        'storageLocation' => "/",
        'id' => null,
        'firstLoginTimestamp' => -1,
        'lastLoginTimestamp' => null,
        'lastLogin' => null,
        'backend' => "Database",
        'subadmin' => [],
        'quota' => [
            'free'     => -3,
            'used'     => 0,
            'total'    => -3,
            'relative' => 0,
            'quota'    => -3,
        ],
        'manager' => "",
        'avatarScope' => "v2-federated",
        'email' => null,
        'emailScope' => "v2-federated",
        'additional_mail' => [],
        'additional_mailScope' => [],
        'displayname' => null,
        'display-name' => null,
        'displaynameScope' => null,
        'phone' => "",
        'phoneScope' => "v2-local",
        'address' => "",
        'addressScope' => "v2-local",
        'website' => "",
        'websiteScope' => "v2-local",
        'twitter' => "",
        'twitterScope' => "v2-local",
        'fediverse' => "",
        'fediverseScope' => "v2-local",
        'organisation' => "",
        'organisationScope' => "v2-local",
        'role' => "",
        'roleScope' => "v2-local",
        'headline' => "",
        'headlineScope' => "v2-local",
        'biography' => "",
        'biographyScope' => "v2-local",
        'profile_enabled' => "0",
        'profile_enabledScope' => "v2-local",
        'pronouns' => "",
        'pronounsScope' => "v2-federated",
        'groups' => [],
        'language' => null,
        'locale' => "",
        'notify_email' => null,
        'backendCapabilities' => [
            'setDisplayName' => false,
            'setPassword' => false,
        ],
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // respond to OPTIONS rquests
        if ($req->getMethod() === "OPTIONS") {
            return HTTP::challenge(HTTP::respEmpty(204, [
                'Allow'  => "GET,HEAD",
                'Vary'   => "Accept",
            ]));
        } elseif ($req->getMethod() !== "GET") {
            return HTTP::respEmpty(405, [
                'Allow'  => "GET,HEAD",
                'Vary'   => "Accept",
            ]);
        }
        // try to authenticate
        if ($req->getAttribute("authenticated", false)) {
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        } else {
            return HTTP::respEmpty(401);
        }
        // get the request path only; this is assumed to already be normalized
        //   and will contain the user ID
        $target = parse_url($req->getRequestTarget())['path'] ?? "";
        // perform content negotiation; we'll prefer JSON if the client doesn't care, but for backwards compatibility we must use XML if the client expresses no preference at all
        $type = MimeType::negotiate(self::TYPES, $req->getHeaderLine("Accept")) ?? "application/xml";
        // only administrators can view users other than themselves
        if ($target !== Arsse::$user->id && !$this->isAdmin()) {
            return $this->respond(403, $type);
        }
        // retrieve the user's metadata and format a response
        try {
            // this call will throw an exception if the user does not exist
            $meta = Arsse::$user->propertiesGet($target, false);
            $now = Arsse::$obj->get(\DateTimeImmutable::class)->getTimestamp();
            $data = self::BASE_DATA;
            $data['id'] = $target;
            $data['language'] = $meta['lang'] ?? "en";
            $data['lastLoginTimestamp'] = $now; // we are not session-based, so we just return the current time
            $data['lastLogin'] = $now * 1000;
            $data['displayname'] = $target; // we don't have a display name, but clients will probably rely on this, so we fill it in
            $data['display-name'] = $target;
            if ($meta['admin']) {
                $data['groups'][] = "admin";
            }
            return $this->respond(200, $type, $data);
        } catch (ExceptionConflict $e) {
            return $this->respond(404, $type);
        }
    }

    protected function respond(int $code, string $type, ?array $data = null): ResponseInterface {
        // Nextcloud sends a weird 404 response when it should send 403
        $status = $code === 403 ? 404 : $code;
        $xml = in_array($type, ["application/xml", "text/xml"]);
        $body = [
            'ocs' => [
                'meta' => self::BASE_META[$code],
                'data' => !$data && !$xml ? new \stdClass : $data ?? [], // we need a stdClass for the JSON encoder to return an empty object
            ],
        ];
        // the response formatting code was lifted from the Fever implementation, with changes
        if ($xml) {
            $d = new \DOMDocument("1.0", "utf-8");
            $d->appendChild($this->makeXMLAssoc($body['ocs'], $d->createElement("ocs")));
            return HTTP::respXml($d->saveXML($d->documentElement, \LIBXML_NOEMPTYTAG), $status, ['Content-Type' => "$type; charset=UTF-8"]);
        } else {
            return HTTP::respJson($body, $status, ['Content-Type' => "$type"], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        }
    }

    protected function makeXMLAssoc(array $data, \DOMElement $p): \DOMElement {
        $d = $p->ownerDocument;
        foreach ($data as $k => $v) {
            if (!is_array($v)) {
                $p->appendChild($d->createElement($k, (string) $v));
            } elseif (isset($v[0])) {
                // this is a very simplistic check for an indexed array
                // it would not pass muster in the face of generic data,
                // but we'll assume our code produces only well-ordered
                // indexed arrays
                $p->appendChild($this->makeXMLIndexed($v, $d->createElement($k)));
            } else {
                $p->appendChild($this->makeXMLAssoc($v, $d->createElement($k)));
            }
        }
        return $p;
    }

    protected function makeXMLIndexed(array $data, \DOMElement $p): \DOMElement {
        $d = $p->ownerDocument;
        foreach ($data as $v) {
            if (!is_array($v)) {
                $p->appendChild($d->createElement("element", (string) $v));
            } elseif (isset($v[0])) { // @codeCoverageIgnore
                // this case is never encountered with Nextcloud's output
                $p->appendChild($this->makeXMLIndexed($v, $d->createElement("element"))); // @codeCoverageIgnore
            } else {
                // this case is never encountered with Nextcloud's output
                $p->appendChild($this->makeXMLAssoc($v, $d->createElement("element"))); // @codeCoverageIgnore
            }
        }
        return $p;
    }
}