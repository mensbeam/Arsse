<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

trait ExclusionProperties {
    public $folder = null;
    public $folders = null;
    public $folderShallow = null;
    public $foldersShallow = null;
    public $tag = null;
    public $tags = null;
    public $tagName = null;
    public $tagNames = null;
    public $subscription = null;
    public $subscriptions = null;
    public $edition = null;
    public $editions = null;
    public $article = null;
    public $articles = null;
    public $label = null;
    public $labels = null;
    public $labelName = null;
    public $labelNames = null;
    public $annotationTerms = null;
    public $searchTerms = null;
    public $titleTerms = null;
    public $authorTerms = null;
    public $articleRange = [null, null];
    public $editionRange = [null, null];
    public $modifiedRange = [null, null];
    public $markedRange = [null, null];
}
