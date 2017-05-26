<?php
namespace JKingWeb\Arsse;
const INSTALL = true;
require_once __DIR__."/../bootstrap.php";


$user = "john.doe@example.com";
$pass = "secret";
$_SERVER['PHP_AUTH_USER'] = $user;
$_SERVER['PHP_AUTH_PW'] = $pass;
$conf = new Conf();
$conf->dbSQLite3File = ":memory:";
Data::load($conf);
Data::$db->schemaUpdate();

Data::$user->add($user, $pass);
Data::$user->auth();
Data::$user->authorizationEnabled(false);
Data::$user->rightsSet($user, User\Driver::RIGHTS_GLOBAL_ADMIN);
Data::$user->authorizationEnabled(true);
Data::$db->folderAdd($user, ['name' => 'ook']);
/*Data::$db->subscriptionAdd($user, "http://linuxfr.org/news.atom");
Data::$db->subscriptionPropertiesSet($user, 1, [
    'title'      => "OOOOOOOOK!",
]);*/
(new REST())->dispatch(new REST\Request(
    "POST", "/index.php/apps/news/api/v1-2/feeds/", json_encode(['url'=> "http://linuxfr.org/news.atom"])
));