<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

trait BooleanProperties {
    public $unread = null;
    public $starred = null;
    public $hidden = null;
    public $labelled = null;
    public $annotated = null;
}
