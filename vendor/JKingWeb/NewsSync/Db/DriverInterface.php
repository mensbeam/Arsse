<?php
namespace JKingWeb\NewsSync\Db;

interface DriverInterface {
	function __construct(\JKingWeb\NewsSync\Conf $conf);
	static function driverName(): string;
}