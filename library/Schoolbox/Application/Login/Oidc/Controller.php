<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Application\Login\Oidc;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use CoderCat\JWKToPEM;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGenerator;
use OpenIDConnectClient\OpenIDConnectProvider;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Instance;
use Schoolbox\Url\Factory;
use Schoolbox\Url\Uri;

/**
 * Controller for OpenID Connect logins.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Client $httpClient Required for querying OIDC discovery URL
     */
    public function __construct(private Client $httpClient)
    {
    }

    /**
     * Send an OpenID Connect authorization request to this instance's oAuth
     * provider.
     *
     * @api GET /login/oidc
     * @since 23.1
     *
     * @return void
     */
    public function getSendAuthorizationRequest(): void
    {
        $config = $this->getConfigLoader();

        // Nonce: not required, but can be used and is respected by the JWT decoder
        // TODO generate and store in the session, if it turns out it is required?
        $nonce = null;

        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $oauthOptions = [
            // Which scope is used to identify a user?
            // https://developer.isams.com/isams-developer-documentation/docs/scopes#isams-identity-scopes
            // Request just openid for now
            'scope'         => 'openid',
            'response_type' => 'code token',
            'response_mode' => 'form_post',
            // State for identifying this as an OIDC request.
            // Temporary, so that this can be redird back to here
            'state'         => 'oidc',
            'x-client-sku'  => Instance::APPLICATION_NAME . '-' . INSTANCE_ID,
            'x-client-ver'  => $config->get('site_version'),
        ];

        // Generate a nonce
        $nonceGenerator        = new NonceGenerator();
        $oauthOptions['nonce'] = $nonceGenerator->generate()->getValue();
        $oidcProvider          = $this->buildOidcProvider();
        $authorizationUrl      = $oidcProvider->getAuthorizationUrl($oauthOptions);

        // Get the state generated for you and store it to the session.
        $this->session->set('oauth2state', $oidcProvider->getState());

        // Redirect the user to the authorization URL.
        $this->response = $this->redirectTemporary($authorizationUrl);
    }

    /**
     * Receive an OpenID Connect authorization response from this instance's
     * oAuth provider, and initialize a user session if it succeeded.
     *
     * @api GET /login/oidc
     * @since 23.1
     *
     * @return void
     */
    public function postReceiveAuthorizationResponse(): void
    {
        // TODO check oauth2state

        // client id / secret gets passed via Basic auth
        $accessToken = $this->buildOidcProvider()->getAccessToken(
            'authorization_code',
            ['code' => $this->requestPost->get('code')]
        );

        // TODO check redirect URI set in state, and redir there?
        $loginUri = (new Uri())
            ->withPath($this->routeGenerator->generate('api.initSessionFromQueryString'))
            ->withQuery(
                http_build_query([
                    'jwt'      => $accessToken->getIdToken()->toString(),
                    'redirect' => '/'
                ])
            );
        $this->response = $this->redirectTemporary($loginUri->__toString());
    }

    /**
     * Build an OIDC provider object.
     *
     * Queries the OIDC provider's discovery URI to find the JWKS URI for the
     * provider, and then converts the JWKS into an actual set of public keys to
     * be used by the OIDC provider.
     *
     * @return OpenIDConnectProvider
     * @throws JWKToPEM\Exception\JWKConverterException if the JWKS couldn't be parsed into public keys
     * @throws JWKToPEM\Exception\Base64DecodeException if the JWKS couldn't be base-64 decoded
     * @throws GuzzleException                          if the request to either the OIDC provider's discovery or JWKS
     *                                                  URIs failed
     * @throws RouteNotFound                            if somehow the route to the redirect URI wasn't found?
     */
    private function buildOidcProvider(): OpenIDConnectProvider
    {
        $config         = $this->getConfigLoader();
        $oauthIssuerUri = new Uri($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER));
        $clientId       = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
        $clientSecret   = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_SECRET);

        // Redirect URI: here, I suppose?
        $redirectUri = (new Factory($config))
            ->buildFullyQualified()
            ->withPath($this->routeGenerator->generate('loginOidc.postReceiveAuthorizationResponse'))
        ;

        // These should be discoverable using OIDC discovery:
        // https://github.com/steverhoades/oauth2-openid-connect-client/pull/37
        // implements this, but it is not yet merged.
        // For now I will fetch the contents manually.
        $oidcDiscoveryUri  = $oauthIssuerUri->withPath($oauthIssuerUri->getPath() . '/.well-known/openid-configuration');
        $oidcConfigRequest = $this->httpClient->get($oidcDiscoveryUri->__toString());
        $oidcConfig        = json_decode($oidcConfigRequest->getBody()->getContents(), true);
        $jwksRequest       = $this->httpClient->get($oidcConfig['jwks_uri']);
        $jwks              = json_decode($jwksRequest->getBody()->getContents(), true);
        $jwkConverter      = new JWKToPEM\JWKConverter();
        return new OpenIDConnectProvider(
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
    }
}
