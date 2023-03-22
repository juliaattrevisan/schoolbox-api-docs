<?php

/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use CoderCat\JWKToPEM\JWKConverter;
use GuzzleHttp\Client;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGenerator;
use OpenIDConnectClient\OpenIDConnectProvider;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Instance;
use Schoolbox\Url\Uri;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new Schoolbox\Application\WebRequest\Kernel();

/** @var IConfigLoader $config */
$config = $kernel->get('config');

// Configurables
$oauthIssuerUri = new Uri($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER));
$clientId       = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
$clientSecret   = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_SECRET);
$idClaim        = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS);

// Which scope is used to identify a user?
// https://developer.isams.com/isams-developer-documentation/docs/scopes#isams-identity-scopes
// Request just openid for now
$scopes = 'openid';

// For this place:
// hardcode redirect URI, as iSAMS hardcoded it and I can't change it
$redirectUri = new Uri('https://alpha.dev.schoolbox.cloud/');

// These should be discoverable using OIDC discovery:
// https://github.com/steverhoades/oauth2-openid-connect-client/pull/37
// implements this, but it is not yet merged.
// For now I will fetch the contents manually.
$oidcDiscoveryUri = $oauthIssuerUri->withPath($oauthIssuerUri->getPath() . '/.well-known/openid-configuration');

/** @var Client $httpClient */
$httpClient        = $kernel->get('httpClient');
$oidcConfigRequest = $httpClient->get($oidcDiscoveryUri->__toString());
$oidcConfig        = json_decode($oidcConfigRequest->getBody()->getContents(), true);
$jwksRequest       = $httpClient->get($oidcConfig['jwks_uri']);
$jwks              = json_decode($jwksRequest->getBody()->getContents(), true);
$jwkConverter      = new JWKConverter();
$oauthProvider     = new OpenIDConnectProvider(
    [
        'clientId'                => $clientId,
        'clientSecret'            => $clientSecret,
        'redirectUri'             => $redirectUri->__toString(),
        'idTokenIssuer'           => $oauthIssuerUri->__toString(),
        'urlAuthorize'            => $oidcConfig['authorization_endpoint'],
        'urlAccessToken'          => $oidcConfig['token_endpoint'],
        'urlResourceOwnerDetails' => $oidcConfig['userinfo_endpoint'],
        'publicKey'               => $jwkConverter->multipleToPEM($jwks['keys']),
    ],
    [
        'signer' => new Sha256(),
    ]
);


// Nonce: not required, but can be used and is respected by the JWT decoder
// TODO generate and store in the session, if it turns out it is required?
$nonce = null;


// Step 1: redirect to auth URL if there was no code
if (!isset($_POST['code'])) {
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $oauthOptions = [
        'scope'         => $scopes,
        'response_type' => 'code token',
        'response_mode' => 'form_post',
        // State for identifying this as an OIDC request.
        // Temporary, so that this can be redird back to here
        'state'         => 'oidc',
        'x-client-SKU'  => Instance::APPLICATION_NAME . '-' . INSTANCE_ID,
        'x-client-ver'  => $config->get('site_version'),
    ];
    // Generate a nonce
    $nonceGenerator        = new NonceGenerator();
    $oauthOptions['nonce'] = $nonceGenerator->generate()->getValue();
    $authorizationUrl      = $oauthProvider->getAuthorizationUrl($oauthOptions);

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $oauthProvider->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;
}

// Step 1a: exit if state is invalid
//if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
//    if (isset($_SESSION['oauth2state'])) {
//        unset($_SESSION['oauth2state']);
//    }
//
//    exit('Invalid state');
//}


// Step 2: if there is a code, swap it for an auth token
try {
    echo '<a href="/oidc.php">Try again</a>';


    // client id / secret gets passed via Basic auth
    $accessToken = $oauthProvider->getAccessToken(
        'authorization_code',
        ['code' => $_POST['code']]
    );
    echo '<h1>Access Token</h1>';
    echo '<pre>' . json_encode($accessToken, JSON_PRETTY_PRINT) . '</pre>';


    echo '<h2>Parsed JWT</h2>';
    echo '<pre>' . json_encode($accessToken->getIdToken()->claims()->all(), JSON_PRETTY_PRINT) . '</pre>';

    // Now, attempt to sign in with this token
    echo <<<FORM
    <form action="/api/session" method="post">
        <textarea type="text" name="jwt" style="width: 100%">{$accessToken->getIdToken()->toString()}</textarea>
        <br/>
        <button type="submit">Sign in as "{$accessToken->getIdToken()->claims()->get($idClaim)}"</button>
    </form>
    FORM;
} catch (IdentityProviderException $e) {
    // Failed to get the access token or user details
    exit($e->getMessage());
}
