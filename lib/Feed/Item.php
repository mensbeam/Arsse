<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Feed;

class Item {
    public $id;
    public $url;
    public $title;
    public $author;
    public $publishedDate;
    public $updatedDate;
    public $urlContentHash;
    public $urlTitleHash;
    public $titleContentHash;
    public $content;
    public $scrapedContent;
    public $enclosureUrl;
    public $enclosureType;
    public $categories = [];
}
