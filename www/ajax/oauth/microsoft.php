<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Config\Loader;
use Schoolbox\Resource\Browser\Office365\Tenant;

if (empty($this)) {
    require_once __DIR__ . '/../../../include/Bootstrap.php';
    // nope. go away
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */
$this->setAjax();


// THIS PAGE SHOULD NEGOTIATE AN OAUTH TOKEN FOR THE CURRENT USER (...somehow.)
$sesskey = 'sp_oauth2state';


/* CONF */
$conf = new Loader();

$clientId     = 'dbb4ee95-3af3-4059-b0ac-2f1f026e216f';
$clientSecret = 'IkcMS+0NpOXhbRLYP8Qb1qgj23SE0zamKobY1epvDrU=';
$clientName   = $conf->get('sharepoint_tenant');
/* /CONF */


$provider = new Alaress\OAuth2\Client\Provider\Office365([
    'clientId'      => $clientId,
    'clientSecret'  => $clientSecret,
    'redirectUri'   => $_SERVER['SCRIPT_URI'], // TODO check this will be reliable
    'scopes'        => [ 'MyFiles.Read' ],
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION[$sesskey] = $provider->state;
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION[$sesskey])) {

    unset($_SESSION[$sesskey]);
    header(':', true, 400);
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

    $_SESSION['azureAccessToken'] = $token->accessToken;

    header(':', true, 201);
    die('<html><script type="text/javascript">window.close();</script></html>');
}