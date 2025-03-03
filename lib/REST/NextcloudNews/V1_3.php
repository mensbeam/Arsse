<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\ResponseInterface;

class V1_3 extends V1_2 {
    public function __construct() {
        // the 'items' input key has been renamed 'itemIds'
        $this->validInput['itemIds'] = $this->validInput['items'];
        unset($this->validInput['items']);
        // most PUT calls have been correctly made POST calls instead
        foreach ([
            "/folders/1/read",
            "/feeds/1/move",
            "/feeds/1/rename",
            "/feeds/1/read",
            "/items/read",
            "/items/1/read",
            "/items/1/unread",
            "/items/read/multiple",
            "/items/unread/multiple",
            "/items/1/1/star",
            "/items/1/1/unstar",
            "/items/star/multiple",
            "/items/unstar/multiple",
        ] as $path) {
            $this->paths[$path]['POST'] = $this->paths[$path]['PUT'];
            unset($this->paths[$path]['PUT']);
        }
        // starring is, however, simplified
        $this->paths['/items/1/star'] = $this->paths['/items/1/1/star'];
        unset($this->paths['/items/1/1/star']);
        $this->paths['/items/1/unstar'] = $this->paths['/items/1/1/unstar'];
        unset($this->paths['/items/1/1/unstar']);
    }

    // mark a single article as read
    protected function articleMarkStarred(array $url, array $data): ResponseInterface {
        // initialize the matching context
        $c = new Context;
        $c->edition((int) $url[1]);
        // determine whether to mark starred or unstarred
        $set = ($url[2] === "star");
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
        } catch (ExceptionInput $e) {
            // ID is not valid
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // mark an array of articles as read
    protected function articleMarkReadMulti(array $url, array $data): ResponseInterface {
        // determine whether to mark read or unread
        $set = ($url[1] === "read");
        // initialize the matching context
        $c = new Context;
        $c->editions($data['itemIds'] ?? []);
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => $set], $c);
        } catch (ExceptionInput $e) {
        }
        return HTTP::respEmpty(204);
    }

    // mark an array of articles as starred
    protected function articleMarkStarredMulti(array $url, array $data): ResponseInterface {
        // determine whether to mark starred or unstarred
        $set = ($url[1] === "star");
        // initialize the matching context
        $c = new Context;
        $c->editions($data['itemIds'] ?? []);
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
        } catch (ExceptionInput $e) {
        }
        return HTTP::respEmpty(204);
    }
}
