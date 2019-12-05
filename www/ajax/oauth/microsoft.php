<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Browser\Office365\Tenant;

if (empty($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    // nope. go away
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */
$this->setAjax();


// THIS PAGE SHOULD NEGOTIATE AN OAUTH TOKEN FOR THE CURRENT USER (...somehow.)
$sesskey = 'sp_oauth2state';


/* CONF */
/** @var IConfigLoader $conf */
$conf = $this->getDi()->get('config');

$clientId     = $conf->get('office365_client');
$clientSecret = $conf->get('office365_secret');
$clientName   = $conf->get('office365_tenant');
/* /CONF */

$urlArray = parse_url($_SERVER['SCRIPT_URI']);
$url = $urlArray['scheme'].'://'.$urlArray['host'].$urlArray['path'];

$provider = new Alaress\OAuth2\Client\Provider\Office365([
    'clientId'      => $clientId,
    'clientSecret'  => $clientSecret,
    'redirectUri'   => $url,
    'scopes'        => [ 'MyFiles.Read' ],
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION[$sesskey] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION[$sesskey])) {

    unset($_SESSION[$sesskey]);
    http_response_code(400);
    exit;

} else {

    $tenant = new Tenant($clientName);

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code'     => $_GET['code'],
        'resource' => $tenant->getResource()
    ]);

    // Use this to interact with an API on the users behalf
//    var_dump($token->accessToken); echo "<br/>";

    // Use this to get a new access token if the old one expires
//    var_dump($token->refreshToken); echo "<br/>";

    // Number of seconds until the access token will expire, and need refreshing
//    var_dump($token->expires); echo "<br/>";

    $_SESSION['azureAccessToken'] = $token->getToken();

    http_response_code(201);
    die('<html><script type="text/javascript">window.close();</script></html>');
}
