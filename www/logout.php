<?php
//small script to logout users

require_once '../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (Config::get('saml_slo_enabled') && isset($_SESSION['samlNameId'])) {
    // Initiate SLO
    header('Location: /saml/logout.php');
    exit;
}

session_start();
session_regenerate_id(true);
session_destroy();

//Destroy remember me cookie
$redis = \Alaress\Redis::getClientInstance();
$username = $redis->executeCommand($redis->createCommand('del',array($_COOKIE['remember'])));
setcookie('remember', '', 1, '/');

$samlLogoutUrl = trim(Config::get('saml_logout_url'));

// Replace some dynamic variables in the logout URL if set
$samlLogoutUrl = str_replace('{SCHOOLBOX_ADDRESS}',$_SERVER['SERVER_NAME'],$samlLogoutUrl);

if (Config::get('saml_enabled') && strlen($samlLogoutUrl) > 0) {
    // Redirect to logout location
    header('Location: ' . $samlLogoutUrl);
    exit;
}
else {
    SitePage::localRedir("/login/");
}
