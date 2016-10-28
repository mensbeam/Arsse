<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User;

class DriverInternal implements Driver {
	use InternalFunctions;

	protected $data;
	protected $db;
	protected $functions = [
		"auth"					=> Driver::FUNC_INTERNAL,
		"userList"				=> Driver::FUNC_INTERNAL,
		"userExists"			=> Driver::FUNC_INTERNAL,
		"userAdd"				=> Driver::FUNC_INTERNAL,
		"userRemove"			=> Driver::FUNC_INTERNAL,
		"userPasswordSet"		=> Driver::FUNC_INTERNAL,
		"userPropertiesGet"		=> Driver::FUNC_INTERNAL,
		"userPropertiesSet"		=> Driver::FUNC_INTERNAL,
	];

	static public function create(\JKingWeb\NewsSync\RuntimeData $data): Driver {
		return new static($data);
	}

	public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
		$this->data = $data;
		$this->db = $this->data->db;
	}

	static public function driverName(): string {
		return "Internal";
	}

	public function driverFunctions(string $function = null) {
		if($function===null) return $this->functions;
		if(array_key_exists($function, $this->functions)) {
			return $this->functions[$function];
		} else {
			return Driver::FUNC_NOT_IMPLEMENTED;
		}
	}
}