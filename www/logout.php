<?php
//small script to logout users

require_once('../include/Bootstrap.php');

session_start();
session_destroy();


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
