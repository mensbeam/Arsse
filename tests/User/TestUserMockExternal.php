<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

/** @covers \JKingWeb\Arsse\User */
class TestUserMockExternal extends Test\AbstractTest {
    use Test\User\CommonTests;

    const USER1 = "john.doe@example.com";
    const USER2 = "jane.doe@example.com";

    public $drv = Test\User\DriverExternalMock::class;
}
