<?php

//small script to logout users

use Schoolbox\Auth\Remember;

require_once '../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (Config::get('saml_slo_enabled') && isset($_SESSION['samlNameId'])) {
    // Initiate SLO
    header('Location: /saml/logout.php');
    exit;
}

session_regenerate_id(true);
session_destroy();

// Destroy remember me cookie
$rememberer = new Remember\Remember(
    new Remember\Cookie(
        Remember\Cookie::DEFAULT_COOKIE_NAME,
        $_COOKIE,
        session_get_cookie_params()
    ),
    new Remember\Redis(INSTANCE_ID, \Alaress\Redis::getClientInstance())
);
$rememberer->forgetUsername();

$samlLogoutUrl = trim(Config::get('saml_logout_url'));

// Replace some dynamic variables in the logout URL if set
$samlLogoutUrl = str_replace('{SCHOOLBOX_ADDRESS}', $_SERVER['SERVER_NAME'], $samlLogoutUrl);

if (Config::get('saml_enabled') && strlen($samlLogoutUrl) > 0) {
    // Redirect to logout location
    header('Location: ' . $samlLogoutUrl);
    exit;
} else {
    SitePage::localRedir("/login/");
}
