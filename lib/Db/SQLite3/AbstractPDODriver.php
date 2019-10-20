<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

abstract class AbstractPDODriver extends Driver {
    // this class exists solely so SQLite's PDO driver can call methods of the generic PDO driver via parent::method()
    // if there's a better way to do this, please FIXME ;)
    use \JKingWeb\Arsse\Db\PDODriver;
}
