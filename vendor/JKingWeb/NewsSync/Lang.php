<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use \Webmozart\Glob\Glob;

class Lang {
	const PATH = BASE."locale".DIRECTORY_SEPARATOR;
	const DEFAULT = "en";
	const REQUIRED = [
		'Exception.JKingWeb/NewsSync/Exception.uncoded'                     => 'The specified exception symbol {0} has no code specified in Exception.php',
		'Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing'		=> 'Default language file "{0}" missing',
		'Exception.JKingWeb/NewsSync/Lang/Exception.fileMissing'			=> 'Language file "{0}" is not available',
		'Exception.JKingWeb/NewsSync/Lang/Exception.fileUnreadable'			=> 'Insufficient permissions to read language file "{0}"',
		'Exception.JKingWeb/NewsSync/Lang/Exception.fileCorrupt'			=> 'Language file "{0}" is corrupt or does not conform to expected format',
		'Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing' 			=> 'Message string "{msgID}" missing from all loaded language files ({fileList})',
		'Exception.JKingWeb/NewsSync/Lang/Exception.stringInvalid' 			=> 'Message string "{msgID}" is not a valid ICU message string (language files loaded: {fileList})',
	];

	static protected $requirementsMet = false;
	static protected $synched = false;
	static protected $wanted = self::DEFAULT;
	static protected $locale = "";
	static protected $loaded = [];
	static protected $strings = self::REQUIRED;

	protected function __construct() {}

	static public function set(string $locale = "", bool $immediate = false): string {
		if(!self::$requirementsMet) self::checkRequirements();
		if($locale=="") $locale = self::DEFAULT;
		if($locale==self::$wanted) return $locale;
		$list = self::listFiles();
		if(!in_array(self::DEFAULT, $list)) throw new Lang\Exception("defaultFileMissing", self::DEFAULT);
		self::$wanted = self::match($locale, $list);
		self::$synched = false;
		if($immediate) self::load();
		return self::$wanted;
	}

	static public function get(): string {
		return (self::$locale=="") ? self::DEFAULT : self::$locale;
	}

	static public function msg(string $msgID, $vars = null): string {
		// if we're trying to load the system default language and it fails, we have a chicken and egg problem, so we catch the exception and load no language file instead
		if(!self::$synched) try {self::load();} catch(Lang\Exception $e) {
			if(self::$wanted==self::DEFAULT) {
				self::set();
				self::load();
			} else {
				throw $e;
			} 
		}
		if(!array_key_exists($msgID, self::$strings)) throw new Lang\Exception("stringMissing", ['msgID' => $msgID, 'fileList' => implode(", ",self::$loaded)]);
		// variables fed to MessageFormatter must be contained in array
		$msg = self::$strings[$msgID];
		if($vars===null) {
			return $msg;
		} else if(!is_array($vars)) {
			$vars = [$vars];
		}
		$msg = \MessageFormatter::formatMessage(self::$locale, $msg, $vars);
		if($msg===false) throw new Lang\Exception("stringInvalid", ['msgID' => $msgID, 'fileList' => implode(", ",self::$loaded)]);
		return $msg;
	}

	static public function list(string $locale = "", string $path = self::PATH): array {
		$out = [];
		$files = self::listFiles($path);
		foreach($files as $tag) {
			$out[$tag] = \Locale::getDisplayName($tag, ($locale=="") ? $tag : $locale); 		
		}
		return $out;
	}

	static public function match(string $locale, array $list = null): string {
		if($list===null) $list = self::listFiles();
		$default = (self::$locale=="") ? self::DEFAULT : self::$locale;
		return \Locale::lookup($list,$locale, true, $default);
	}

	static protected function checkRequirements(): bool {
		if(!extension_loaded("intl")) throw new ExceptionFatal("The \"Intl\" extension is required, but not loaded");
		self::$requirementsMet = true;
		return true;
	}
	
	static protected function listFiles(string $path = self::PATH): array {
		$out = Glob::glob($path."*.php");
		$out = array_map(function($file) {
			$file = substr(str_replace(DIRECTORY_SEPARATOR, "/", $file),strrpos($file,"/")+1);
			return strtolower(substr($file,0,strrpos($file,".")));
		},$out);
		natsort($out);
		return $out;
	}

	static protected function load(): bool {
		self::$synched = true;
		if(!self::$requirementsMet) self::checkRequirements();
		// if we've yet to request a locale, just load the fallback strings and return
		if(self::$wanted=="") {
			self::$strings = self::REQUIRED;
			self::$locale = self::$wanted;
			return true;
		}
		// decompose the requested locale from specific to general, building a list of files to load
		$tags = \Locale::parseLocale(self::$wanted);
		$files = [];
		$loaded = [];
		$strings = [];
		while(sizeof($tags) > 0) {
			$files[] = \Locale::composeLocale($tags);
			$tag = array_pop($tags);
		}
		// include the default locale as the base if the most general locale requested is not the default
		if($tag != self::DEFAULT) $files[] = self::DEFAULT;
		// save the list of files to be loaded for later reference
		$loaded = $files;
		// reduce the list of files to be loaded to the minimum necessary (e.g. if we go from "fr" to "fr_ca", we don't need to load "fr" or "en")
		$files = [];
		foreach($loaded as $file) {
			if($file==self::$locale) break;
			$files[] = $file;
		}
		// if we need to load all files, start with the fallback strings
		if($files==$loaded) $strings[] = self::REQUIRED;
		// read files in reverse order
		$files = array_reverse($files);
		foreach($files as $file) {
			if(!file_exists(self::PATH."$file.php")) throw new Lang\Exception("fileMissing", $file);
			if(!is_readable(self::PATH."$file.php")) throw new Lang\Exception("fileUnreadable", $file);
			try {
				ob_start();
				$arr = (include self::PATH."$file.php");
			} catch(\Throwable $e) {
				$arr = null;
			} finally {
				ob_end_clean();
			}
			if(!is_array($arr)) throw new Lang\Exception("fileCorrupt", $file);
			$strings[] = $arr;
		}
		// apply the results and return
		self::$strings = call_user_func_array("array_replace_recursive", $strings);
		self::$loaded = $loaded;
		self::$locale = self::$wanted;
		return true;
	}
}