<?php

//small script to logout users

use Ramsey\Uuid\Uuid;
use Schoolbox\Auth\Remember;
use Schoolbox\Oidc\Server\BackChannelLogout;
use Schoolbox\Oidc\Server\RelyingParty\Grant;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$logoutFactory       = null;
$logoutClient        = null;
$backChannelRequests = [];

// Find every relying party the current user has granted access to,
// and log them out if it supports back-channel logout.
$relyingPartyGrantMapper = Grant\Mapper::getInstance();
$currentUser             = $_SESSION['user'] ?? null;
if ($currentUser instanceof \Schoolbox\User) {
    /** @var Grant\Model $relyingPartyGrant */
    foreach ($relyingPartyGrantMapper->findByUser($currentUser) as $relyingPartyGrant) {
        // Does this client support logout?
        // If so, craft a logout token and send it
        $client = $relyingPartyGrant->getRelyingParty();
        if ($client->getBackChannelLogoutUri()) {
            // Create logout factory and client on demand only
            $logoutFactory ??= new BackChannelLogout\RequestFactory(
                $kernel->get('user.tokenBuilder'),
                $kernel->get('httpFactory'),
            );
            $request = $logoutFactory->build($currentUser, $relyingPartyGrant, Uuid::uuid4()->toString());

            $logoutClient                          ??= new BackChannelLogout\Client($kernel->get('httpClient'));
            $backChannelRequests[$client->getId()] = $logoutClient->send($request);
        }
    }
}

// Wait for any back-channel requests to complete
if (!empty($backChannelRequests)) {
    \GuzzleHttp\Promise\Utils::settle($backChannelRequests)->wait();
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
