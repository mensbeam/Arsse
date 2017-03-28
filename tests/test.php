<?php
namespace JKingWeb\Arsse;
const INSTALL = true;
require_once "../bootstrap.php";


$user = "john.doe@example.com";
$pass = "secret";
$_SERVER['PHP_AUTH_USER'] = $user;
$_SERVER['PHP_AUTH_PW'] = $pass;
$conf = new Conf();
$conf->dbSQLite3File = ":memory:";
$conf->userAuthPreferHTTP = true;
Data::load($conf);
Data::$db->schemaUpdate();

Data::$user->add($user, $pass);
Data::$user->auth();
Data::$user->authorizationEnabled(false);
Data::$user->rightsSet($user, User\Driver::RIGHTS_GLOBAL_ADMIN);
Data::$user->authorizationEnabled(true);
Data::$db->folderAdd($user, ['name' => 'ook']);
Data::$db->subscriptionAdd($user, "http://www.tbray.org/ongoing/ongoing.atom");