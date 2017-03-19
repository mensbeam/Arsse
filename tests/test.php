<?php
namespace JKingWeb\NewsSync;
const INSTALL = true;
require_once "../bootstrap.php";

$user = "john.doe@example.com";
$pass = "secret";
$_SERVER['PHP_AUTH_USER'] = $user;
$_SERVER['PHP_AUTH_PW'] = $pass;
$conf = new Conf();
$conf->dbSQLite3File = ":memory:";
$conf->userAuthPreferHTTP = true;
$data = new RuntimeData($conf);
$data->db->schemaUpdate();

(new REST($data))->dispatch("GET", "/index.php/apps/news/api/", "");
exit;



$data->user->add($user, $pass);
$data->user->auth();
$data->user->authorizationEnabled(false);
$data->user->rightsSet($user, User\Driver::RIGHTS_GLOBAL_ADMIN);
$data->user->authorizationEnabled(true);
$data->db->folderAdd($user, ['name' => 'ook']);
$data->db->subscriptionAdd($user, "http://www.tbray.org/ongoing/ongoing.atom");