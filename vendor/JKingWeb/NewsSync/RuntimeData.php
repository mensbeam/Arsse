<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class RuntimeData {
	protected $conf;
	protected $db;
	protected $auth;

	public function __construct(Conf $conf) {
		$this->conf = $conf;
		Lang::set($conf->lang);
		$this->db = new Database($this->conf);
		//$this->auth = new Authenticator($this);
	}
}