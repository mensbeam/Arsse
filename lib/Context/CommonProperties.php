<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Context;

trait CommonProperties {
    protected $parent = null;
    protected $props = [];

    public $folder = null;
    public $folders = [];
    public $folderShallow = null;
    public $foldersShallow = [];
    public $tag = null;
    public $tags = [];
    public $tagName = null;
    public $tagNames = [];
    public $subscription = null;
    public $subscriptions = [];
    public $edition = null;
    public $editions = [];
    public $article = null;
    public $articles = [];
    public $label = null;
    public $labels = [];
    public $labelName = null;
    public $labelNames = [];
    public $annotationTerms = [];
    public $searchTerms = [];
    public $titleTerms = [];
    public $authorTerms = [];
    public $articleRange = [null, null];
    public $editionRange = [null, null];
    public $modifiedRange = [null, null];
    public $modifiedRanges = [];
    public $markedRange = [null, null];
    public $markedRanges = [];
    public $addedRange = [null, null];
    public $addedRanges = [];
    public $publishedRange = [null, null];
    public $publishedRanges = [];
}
