<?php

//small script to logout users

use Lcobucci\JWT\Token\RegisteredClaims;
use Ramsey\Uuid\Uuid;
use Schoolbox\Auth\Remember;
use Schoolbox\Url\Factory;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// Begin back-channel logout requests
foreach ($_SESSION[\Schoolbox\Oidc\Server\Controller::SESSION_OIDC_SERVER_CLIENTS] ?? [] as $clientId) {
    // Does this client support logout?
    // If so, craft a logout token and send it
    $token = [
        RegisteredClaims::AUDIENCE        => $clientId,
        RegisteredClaims::ISSUER          => (new Factory($kernel->get('config')))->buildFullyQualified(),
        RegisteredClaims::SUBJECT         => $_SESSION['user']->getUsername(),
        RegisteredClaims::ISSUED_AT       => Alaress_Date_Format::now()->getUnixTimeStamp(),
        RegisteredClaims::EXPIRATION_TIME => Alaress_Date_Format::now()->addMinutes(5)->getUnixTimeStamp(),
        RegisteredClaims::ID              => Uuid::uuid4()->toString(),
        'events'                          => ['http://schemas.openid.net/event/backchannel-logout' => []],
    ];
}

if (Config::get('saml_slo_enabled') && isset($_SESSION['samlNameId'])) {
    // Initiate SLO
    header('Location: /saml/logout.php');
    exit;
}

session_regenerate_id(true);
session_destroy();

// Destroy remember me cookie
/** @var Remember\Remember $rememberer */
$rememberer = $kernel->get('session.rememberer');
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
