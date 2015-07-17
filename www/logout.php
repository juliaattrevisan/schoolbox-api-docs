<?php
//small script to logout users

require_once('../include/Bootstrap.php');

session_start();
session_regenerate_id(true);
session_destroy();

//Destroy remember me cookie
$redis = \Alaress\Redis::getClientInstance();
$username = $redis->executeCommand($redis->createCommand('del',array($_COOKIE['remember'])));
setcookie('remember', '', 1, '/');


$samlLogoutUrl = trim(Cfg::get('saml_logout_url'));

// Replace some dynamic variables in the logout URL if set
$samlLogoutUrl = str_replace('{SCHOOLBOX_ADDRESS}',$_SERVER['SERVER_NAME'],$samlLogoutUrl);

if (strlen($samlLogoutUrl)>0) {
    header('Location: ' . $samlLogoutUrl);
    exit;
}
else {
    SitePage::localRedir("/login/");
}
