<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class RuntimeData {
	protected $conf;
	protected $db;
	protected $auth;

	public function __construct(Conf $conf) {
		Lang::set();
		$this->conf = $conf;
		//$this->db = new Database($this);
		//$this->auth = new Authenticator($this);
	}
}