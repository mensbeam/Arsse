<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;


class TestUserMockInternal extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\User\CommonTests;

    const USER1 = "john.doe@example.com";
    const USER2 = "jane.doe@example.com";

    public $drv = Test\User\DriverInternalMock::class;

    function setUpSeries() {
        Data::$db = null;
    }
}
