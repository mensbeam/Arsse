<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

const BASE = __DIR__.DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

if(!defined(NS_BASE."INSTALL")) define(NS_BASE."INSTALL", false);

spl_autoload_register(function ($class) {
	if($class=="SimplePie") return;
	$file = str_replace("\\", DIRECTORY_SEPARATOR, $class);
    $file = BASE."vendor".DIRECTORY_SEPARATOR.$file.".php";
	if (file_exists($file)) {
		require_once $file;
    }
});

ignore_user_abort(true);

$data = new RuntimeData(new Conf());