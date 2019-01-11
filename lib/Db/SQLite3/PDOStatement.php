<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class PDOStatement extends \JKingWeb\Arsse\Db\PDOStatement {
    use ExceptionBuilder;
    use \JKingWeb\Arsse\Db\PDOError;
}
