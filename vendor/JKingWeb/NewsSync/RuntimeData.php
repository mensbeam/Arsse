<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class RuntimeData {
	public $conf;
	public $db;
	public $auth;

	public function __construct(Conf $conf) {
		$this->conf = $conf;
		Lang::set($conf->lang);
		$this->db = new Database($this->conf);
		//$this->auth = new Authenticator($this);
	}
}