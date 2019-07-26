<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Statement {
    const TYPES = [
        'int'              => self::T_INTEGER,
        'integer'          => self::T_INTEGER,
        'float'            => self::T_FLOAT,
        'double'           => self::T_FLOAT,
        'real'             => self::T_FLOAT,
        'numeric'          => self::T_FLOAT,
        'datetime'         => self::T_DATETIME,
        'timestamp'        => self::T_DATETIME,
        'blob'             => self::T_BINARY,
        'bin'              => self::T_BINARY,
        'binary'           => self::T_BINARY,
        'text'             => self::T_STRING,
        'string'           => self::T_STRING,
        'str'              => self::T_STRING,
        'bool'             => self::T_BOOLEAN,
        'boolean'          => self::T_BOOLEAN,
        'bit'              => self::T_BOOLEAN,
        'strict int'       => self::T_NOT_NULL + self::T_INTEGER,
        'strict integer'   => self::T_NOT_NULL + self::T_INTEGER,
        'strict float'     => self::T_NOT_NULL + self::T_FLOAT,
        'strict double'    => self::T_NOT_NULL + self::T_FLOAT,
        'strict real'      => self::T_NOT_NULL + self::T_FLOAT,
        'strict numeric'   => self::T_NOT_NULL + self::T_FLOAT,
        'strict datetime'  => self::T_NOT_NULL + self::T_DATETIME,
        'strict timestamp' => self::T_NOT_NULL + self::T_DATETIME,
        'strict blob'      => self::T_NOT_NULL + self::T_BINARY,
        'strict bin'       => self::T_NOT_NULL + self::T_BINARY,
        'strict binary'    => self::T_NOT_NULL + self::T_BINARY,
        'strict text'      => self::T_NOT_NULL + self::T_STRING,
        'strict string'    => self::T_NOT_NULL + self::T_STRING,
        'strict str'       => self::T_NOT_NULL + self::T_STRING,
        'strict bool'      => self::T_NOT_NULL + self::T_BOOLEAN,
        'strict boolean'   => self::T_NOT_NULL + self::T_BOOLEAN,
        'strict bit'       => self::T_NOT_NULL + self::T_BOOLEAN,
    ];
    const T_INTEGER = 1;
    const T_STRING = 2;
    const T_BOOLEAN = 3;
    const T_DATETIME = 4;
    const T_FLOAT = 5;
    const T_BINARY = 6;
    const T_NOT_NULL = 100;

    public function run(...$values): Result;
    public function runArray(array $values = []): Result;
    public function retype(...$bindings): bool;
    public function retypeArray(array $bindings): bool;
}
