<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Lang;

class Exception extends \Exception {
    protected $symbol;
    protected $params;

    public function __construct(string $msgID = "", $vars = null, ?\Throwable $e = null) {
        $this->symbol = $msgID;
        $this->params = (array) ($vars ?? []);
        $msg = (Arsse::$lang ?? new Lang)->msg("API.Reader.Error.".$this->symbol, $vars);
        parent::__construct($msg, 0, $e);
    }

    public function getSymbol(): string {
        return $this->symbol;
    }

    public function getParams(): array {
        return $this->params;
    }
}
