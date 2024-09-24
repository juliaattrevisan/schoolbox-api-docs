<?php

//small script to logout users

use Http\Discovery\Psr17Factory;
use Ramsey\Uuid\Uuid;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Auth\Remember;
use Schoolbox\Oidc\Server\RelyingParty\Mapper;
use Schoolbox\Oidc\Server\Request\LogoutFactory;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// Begin back-channel logout requests
foreach ($_SESSION[\Schoolbox\Oidc\Server\Controller::SESSION_OIDC_SERVER_CLIENTS] ?? [] as $clientId) {
    // Does this client support logout?
    // If so, craft a logout token and send it
    $client        = Mapper::getInstance()->findByClientId($clientId);
    $jwtBuilder    = null;
    $httpFactory   = null;
    $logoutFactory = null;
    if ($client?->getBackChannelLogoutUri()) {
        // Create logout factory on demand only
        /** @var Jwt $jwtBuilder */
        /** @var Psr17Factory $httpFactory */
        $jwtBuilder    ??= $kernel->get('user.tokenBuilder');
        $httpFactory   ??= $kernel->get('httpFactory');
        $logoutFactory ??= new LogoutFactory($jwtBuilder, $httpFactory);
        $request       = $logoutFactory->build($_SESSION['user'], $client, Uuid::uuid4()->toString());

        /** @var \GuzzleHttp\ClientInterface $httpClient */
        $httpClient = $kernel->get('httpClient');

        // Consider retry if this fails?
        $httpClient->sendAsync($request);
    }
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
