<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Okta\JwtVerifier\Discovery\Oidc;
use Okta\JwtVerifier\JwtVerifierBuilder;
use Schoolbox\Auth\Adapter\FirebasePhpJwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Zend\Uri\Uri;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Schoolbox\Application\WebRequest\Kernel();

/** @var IConfigLoader $config */
$config        = $kernel->get('config');
$oauthIssuer   = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER);
$clientId      = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
$clientSecret  = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_SECRET);
$redirectUri   = (new Uri())
    ->setScheme($_SERVER['REQUEST_SCHEME'] ?? 'https')
    ->setHost($config->get('site_hostname') . '.' . $config->get('site_domain'))
    ->setPath('/oauth.php');
$oauthProvider = new GenericProvider([
    'clientId'                => $clientId,
    'clientSecret'            => $clientSecret,
    'redirectUri'             => $redirectUri->toString(),
    'urlAuthorize'            => $oauthIssuer . '/api/oauth/authorize',
    'urlAccessToken'          => $oauthIssuer . '/api/oauth/token',
    'urlResourceOwnerDetails' => $oauthIssuer . '/api/oauth/userinfo',
]);
$oauthProvider->setOptionProvider(new HttpBasicAuthOptionProvider());


// Nonce: not required, but can be used and is respected by the JWT decoder
// TODO generate and store in the session, if it turns out it is required?
$nonce = null;


// Step 1: redirect to auth URL if there was no code
if (!isset($_GET['code'])) {
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $oauthOptions     = [
        'scope'         => 'openid profile email https://scopes.cloudwork.id/profile',
        'response_type' => 'code',
        'response_mode' => 'query',
        'claims'        => json_encode([
            'userinfo' => [
                'https://cloudwork.id/claims/sis_id' => null,
            ],
            'id_token' => [
                'https://cloudwork.id/claims/sis_id' => null,
                'email'                              => null,
                'preferred_username'                 => null,
            ],
        ])
    ];
    if (isset($nonce)) {
        $oauthOptions['nonce'] = $nonce;
    }
    $authorizationUrl = $oauthProvider->getAuthorizationUrl($oauthOptions);

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $oauthProvider->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;
}

// Step 1a: exit if state is invalid
if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }

    exit('Invalid state');
}


// Step 2: if there is a code, swap it for an auth token
try {
    echo '<a href="https://vagrant.dev.schoolbox.cloud/oauth.php">Try again</a>';


    // client id / secret gets passed via Basic auth
    $accessToken = $oauthProvider->getAccessToken(
        'authorization_code',
        ['code' => $_GET['code']]
    );
    echo '<h1>Access Token</h1>';
    echo '<pre>'.json_encode($accessToken, JSON_PRETTY_PRINT).'</pre>';


    // Step 2a: get token owner details
    $resourceOwner = $oauthProvider->getResourceOwner($accessToken);
    echo '<h2>Resource Owner</h2>';
    echo '<pre>'.json_encode($resourceOwner->toArray(), JSON_PRETTY_PRINT).'</pre>';


    // Step 2b: parse the JWT
    $jwtVerifierBuilder = (new JwtVerifierBuilder())
        // I guess this is configurable.
        // We'll probably have multiple oAuth providers we accept too
        ->setIssuer($oauthIssuer)
        // For now, I must hardcode the key ID, as it's not in the JWT header
        ->setAdaptor(new FirebasePhpJwt($config->get(ConfigMapper::_CLOUDWORK_OAUTH_JWT_KEY_ID)))
        ->setDiscovery(new Oidc())
        ->setAudience([$clientId]);
    if (isset($nonce)) {
        $jwtVerifierBuilder = $jwtVerifierBuilder->setNonce($nonce);
    }
    $jwtVerifier = $jwtVerifierBuilder->build();

    echo '<h2>Parsed JWT</h2>';
    echo '<pre>'.json_encode($jwtVerifier->verify($accessToken->getValues()['id_token']), JSON_PRETTY_PRINT).'</pre>';


} catch (IdentityProviderException $e) {
    // Failed to get the access token or user details
    exit($e->getMessage());
}
