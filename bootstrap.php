<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

const BASE = __DIR__.DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

if(!defined(NS_BASE."INSTALL")) define(NS_BASE."INSTALL", false);

require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";
ignore_user_abort(true);