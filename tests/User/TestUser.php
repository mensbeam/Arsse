<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestUser extends \PHPUnit\Framework\TestCase {
    use Test\Tools;
    
	protected $data;

    function setUp() {
		$conf = new Conf();
		$conf->userDriver = Test\User\DriverInternalMock::class;
		$this->data = new Test\RuntimeData($conf);
		$this->data->user = new User($this->data);
	}
}
