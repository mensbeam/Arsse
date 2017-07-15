<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Service;

interface Driver {
    static function driverName(): string;
    static function requirementsMet(): bool;
    function queue(int ...$feeds): int;
    function exec(): int;
    function clean(): bool;
}