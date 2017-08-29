<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Service;

interface Driver {
    public static function driverName(): string;
    public static function requirementsMet(): bool;
    public function queue(int ...$feeds): int;
    public function exec(): int;
    public function clean(): bool;
}
