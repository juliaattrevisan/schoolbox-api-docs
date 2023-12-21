<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\Login\Oidc;

use Alaress\Codec\ICodec;
use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\Exception as ControllerException;
use Aura\Router\Exception\RouteNotFound;
use CoderCat\JWKToPEM;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use OpenIDConnectClient\OpenIDConnectProvider;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Instance;
use Schoolbox\Oidc\CacheHelper;
use Schoolbox\Url\Factory;
use Schoolbox\Url\Uri;

/**
 * Controller for OpenID Connect logins.
 */
class Controller extends Base
{
    /**
     * Session keys in which the OIDC state and nonce are stored.
     */
    private const SESSION_STATE_KEY = 'oidcState';
    private const SESSION_NONCE_KEY = 'oidcNonce';

    /**
     * Constructor
     *
     * @param Client         $httpClient Required for querying discovery URL
     * @param ICodec         $codec      Required for encoding/decoding state
     * @param CacheInterface $cache      Required for caching discovery response
     *                                   and keys
     */
    public function __construct(
        private Client $httpClient,
        private ICodec $codec,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Send OIDC auth requests and receive responses via the same endpoint.
     *
     * @api GET /login/oidc
     * @since 23.1
     *
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     * @throws ControllerException
     */
    public function getAuthorization(): void
    {
        $code = $this->requestPost->get('code') ?? $this->requestQuery->get('code');
        if (isset($code)) {
            $this->receiveAuthorizationResponse($code);
        } else {
            $this->sendAuthorizationRequest();
        }
    }

    /**
     * Send OIDC auth requests and receive responses via the same endpoint.
     *
     * @api POST /login/oidc
     * @since 23.1
     *
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     * @throws ControllerException
     */
    public function postAuthorization(): void
    {
        $this->getAuthorization();
    }

    /**
     * Send an OpenID Connect authorization request to this instance's oAuth
     * provider.
     *
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     */
    private function sendAuthorizationRequest(): void
    {
        $nonce        = Uuid::uuid4()->toString();
        $oauthOptions = [
            // Which scope is used to identify a user?
            // https://developer.isams.com/isams-developer-documentation/docs/scopes#isams-identity-scopes
            // Request just openid for now
            'scope'         => 'openid',
            'response_type' => 'code token',
            'response_mode' => 'form_post',
            'state'         => $this->buildState($nonce),
            'nonce'         => $nonce,
            'x-client-sku'  => Instance::APPLICATION_NAME . '-' . INSTANCE_ID,
            'x-client-ver'  => $this->getConfigLoader()->get('site_version'),
        ];

        // Build provider
        $oidcProvider     = $this->buildOidcProvider();
        $authorizationUrl = $oidcProvider->getAuthorizationUrl($oauthOptions);

        // Get the state and nonce generated for you and store it to the session
        $this->session->set(self::SESSION_STATE_KEY, $oidcProvider->getState());
        $this->session->set(self::SESSION_NONCE_KEY, $nonce);

        // Redirect the user to the authorization URL
        $this->response = $this->redirectTemporary($authorizationUrl);
    }

    /**
     * Receive an OpenID Connect authorization response from this instance's
     * oAuth provider, and initialize a user session if it succeeded.
     *
     * @param string $code The code from the auth response.
     * @return void
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     * @throws ControllerException
     */
    private function receiveAuthorizationResponse(string $code): void
    {
        // check state
        $requestState = $this->requestPost->get('state') ?? $this->requestQuery->get('state');
        $sessionState = $this->session->get(self::SESSION_STATE_KEY);
        $this->session->set(self::SESSION_STATE_KEY, null);
        if (!isset($requestState, $sessionState) || $requestState !== $sessionState) {
            $this->sendError('State sent to and received from OIDC provider do not match');
            return;
        }

        // convert auth code to access token
        $accessToken = $this->buildOidcProvider()->getAccessToken(
            'authorization_code',
            ['code' => $code]
        );

        // check nonce in ID token
        // TODO this should be done in $loginUri, but that's too disruptive a
        //      change right now
        $sessionNonce = $this->session->get(self::SESSION_NONCE_KEY);
        $this->session->set(self::SESSION_NONCE_KEY, null);
        $idTokenNonce = $accessToken->getIdToken()->claims()->get('nonce');
        if (!isset($idTokenNonce, $sessionNonce) || $idTokenNonce !== $sessionNonce) {
            $this->sendError('Nonce sent to and received from OIDC provider do not match');
            return;
        }

        // Init the session from the ID token received from the OIDC provider
        $decodedState = $this->codec->decode($requestState);
        $loginUri     = (new Uri())
            ->withPath($this->routeGenerator->generate('api.initSessionFromQueryString'))
            ->withQuery(
                http_build_query([
                    'jwt'      => $accessToken->getIdToken()->toString(),
                    'redirect' => $decodedState['redirect'] ?? '/',
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
     * @throws ClientExceptionInterface                 if the OIDC config or JWKS couldn't be retrieved
     */
    private function buildOidcProvider(): OpenIDConnectProvider
    {
        $config                   = $this->getConfigLoader();
        $oauthIdentityProviderUri = new Uri($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER));
        $clientId                 = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
        $clientSecret             = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_SECRET);

        // Redirect URI: here, I suppose?
        $redirectUri = (new Factory($config))
            ->buildFullyQualified()
            ->withPath($this->routeGenerator->generate('loginOidc.getAuthorization'))
        ;

        // OIDC configuration should be discoverable using OIDC discovery:
        // https://github.com/steverhoades/oauth2-openid-connect-client/pull/37
        // implements this, but it is not yet merged.
        // For now I will fetch the configuration manually, caching the response
        // if possible.
        $oidcCache = new CacheHelper($this->httpClient, $this->cache);
        try {
            $oidcConfig = $oidcCache->retrieveOidcConfig($oauthIdentityProviderUri);
            $jwks       = $oidcCache->retrieveJsonWebKeySet($oauthIdentityProviderUri);
        } catch (InvalidArgumentException $e) {
            throw new SchoolboxException('Could not cache OIDC configuration or public key', 0, $e, 500);
        }

        $jwkConverter = new JWKToPEM\JWKConverter();
        $publicKey    = $jwkConverter->multipleToPEM($jwks['keys']);
        return new OpenIDConnectProvider(
            [
                'clientId'                => $clientId,
                'clientSecret'            => $clientSecret,
                'redirectUri'             => $redirectUri->__toString(),
                'idTokenIssuer'           => $oauthIdentityProviderUri->__toString(),
                'urlAuthorize'            => $oidcConfig['authorization_endpoint'],
                'urlAccessToken'          => $oidcConfig['token_endpoint'],
                'urlResourceOwnerDetails' => $oidcConfig['userinfo_endpoint'],
                'publicKey'               => $publicKey,
            ],
            [
                'signer' => new Sha256(),
            ]
        );
    }

    /**
     * Build a state value containing a nonce and a path to redirect to.
     *
     * @param string $nonce
     * @return string
     */
    private function buildState(string $nonce): string
    {
        $payload = [
            'redirect' => $this->requestQuery->get('page') ?? '/',
            'nonce'    => $nonce,
        ];
        return $this->codec->encode($payload);
    }
}
