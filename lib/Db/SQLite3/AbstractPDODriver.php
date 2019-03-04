<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

abstract class AbstractPDODriver extends Driver {
    use \JKingWeb\Arsse\Db\PDODriver;
}
