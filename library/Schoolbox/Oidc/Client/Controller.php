<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Client;

use Alaress\Codec\ICodec;
use Alaress\Web\Controller\Base;
use Alaress\Web\Controller\Exception as ControllerException;
use Aura\Router\Exception\RouteNotFound;
use CoderCat\JWKToPEM;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Client\Provider\AbstractProvider;
use OpenIDConnectClient\AccessToken;
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
 *
 * This implements Schoolbox as a relying party (e.g. a client) for an OpenID
 * provider.
 */
class Controller extends Base
{
    /**
     * Session keys in which the OIDC state, PKCE and nonce are stored.
     */
    private const SESSION_STATE_KEY     = 'oidcState';
    private const SESSION_PKCE_CODE_KEY = 'oidcPkceCode';
    private const SESSION_NONCE_KEY     = 'oidcNonce';

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
        // What was received?
        // * an ID token? send it straight to login if so, but not before
        //   decoding and checking nonce
        // * an access token? verify it if so, and create a session for the
        //   identified user
        // * an auth code? if so, exchange for an access token
        // * neither: send the authorization request
        $idToken      = $this->requestPost->get('id_token') ?? $this->requestQuery->get('id_token');
        $requestState = $this->requestPost->get('state') ?? $this->requestQuery->get('state');
        if (isset($idToken)) {
            $decodedIdToken = $this->decodeAccessToken($idToken);
            // Check nonce, and then redirect
            if (!$this->verifyNonce($decodedIdToken['nonce'] ?? null)) {
                $this->sendError('Nonce sent to and received from OIDC provider do not match');
                return;
            }
            $this->redirectToSessionInit($requestState, $idToken);
            return;
        }

        $accessToken = $this->requestPost->get('access_token') ?? $this->requestQuery->get('access_token');
        if (isset($accessToken)) {
            // Exchange for an ID token, and redirect to it: first check state
            if (!$this->verifyState($requestState)) {
                $this->sendError('State sent to and received from OIDC provider do not match');
                return;
            }
            $idToken = $this->decodeAccessToken($accessToken);
        }

        $code = $this->requestPost->get('code') ?? $this->requestQuery->get('code');
        if (isset($code)) {
            // Authorize user, but first check state
            if (!$this->verifyState($requestState)) {
                $this->sendError('State sent to and received from OIDC provider do not match');
                return;
            }
            $decodedAccessToken = $this->exchangeAuthorizationCodeForAccessToken($code);

            // check nonce in ID token
            // TODO this should be done in $loginUri, but that's too disruptive
            //      a change right now
            if (!$this->verifyNonce($decodedAccessToken->getIdToken()->claims()->get('nonce'))) {
                $this->sendError('Nonce sent to and received from OIDC provider do not match');
                return;
            }

            // Init the session from the ID token received from the OIDC provider
            $this->redirectToSessionInit($requestState, $decodedAccessToken->getIdToken()->toString());
            return;
        }
        $this->sendAuthorizationRequest();
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
        // Was there an error? If so output something
        $error = $this->requestPost->get('error') ?? $this->requestQuery->get('error');
        if (isset($error)) {
            $this->response = $this->responseFromTemplateUnthemed(
                '@loginOidc/error.twig',
                ['error' => $error],
            );
            return;
        }

        $nonce        = Uuid::uuid4()->toString();
        $oauthOptions = [
            'scope'         => $this->getConfigLoader()->get(ConfigMapper::NAME_OAUTH_SCOPES),
            'response_type' => $this->getConfigLoader()->get(ConfigMapper::NAME_OAUTH_RESPONSE_TYPE),
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
        $this->session->set(self::SESSION_PKCE_CODE_KEY, $oidcProvider->getPkceCode());
        $this->session->set(self::SESSION_NONCE_KEY, $nonce);

        // Redirect the user to the authorization URL
        $this->response = $this->redirectTemporary($authorizationUrl);
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
        $config         = $this->getConfigLoader();
        $oauthIssuerUri = new Uri(
            $config->get(ConfigMapper::NAME_OAUTH_ISSUER)
            ?: $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
        );
        $clientId       = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
        $clientSecret   = $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_SECRET);

        // OIDC configuration should be discoverable using OIDC discovery:
        // https://github.com/steverhoades/oauth2-openid-connect-client/pull/37
        // implements this, but it is not yet merged.
        // For now I will fetch the configuration manually, caching the response
        // if possible.
        $oidcCache = new CacheHelper($this->httpClient, $this->cache);
        try {
            $oidcConfig = $oidcCache->retrieveOidcConfig($oauthIssuerUri);
        } catch (InvalidArgumentException $e) {
            throw new SchoolboxException('Could not cache OIDC configuration or public key', 0, $e, 500);
        }

        // Redirect URI: here, I suppose?
        $redirectUri = (new Factory($config))
            ->buildFullyQualified()
            ->withPath($this->routeGenerator->generate('loginOidc.getAuthorization'));
        return new OpenIDConnectProvider(
            [
                'clientId'                => $clientId,
                'clientSecret'            => $clientSecret,
                'redirectUri'             => $redirectUri->__toString(),
                'idTokenIssuer'           => $oauthIssuerUri->__toString(),
                'urlAuthorize'            => $oidcConfig['authorization_endpoint'],
                'urlAccessToken'          => $oidcConfig['token_endpoint'],
                'urlResourceOwnerDetails' => $oidcConfig['userinfo_endpoint'] ?? null,
                'publicKey'               => (new JWKToPEM\JWKConverter())
                    ->multipleToPEM($this->retrieveJsonWebKeySet()['keys']),
                'pkceMethod'              => AbstractProvider::PKCE_METHOD_S256,
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

    /**
     * Decode an access token from the URL into an object.
     *
     * @param string $token
     * @return array
     */
    private function decodeAccessToken(string $token): array
    {
        $jwks         = $this->retrieveJsonWebKeySet();
        $keySet       = JWK::parseKeySet($jwks, \Schoolbox\API\Token\Jwt::SIGNING_ALGORITHM);
        Jwt::$leeway  = $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_LEEWAY);
        return (array)(Jwt::decode($token, $keySet[\Schoolbox\API\Token\Jwt::KEY_ID]));
    }

    /**
     * Exchange a given auth code for an access token.
     *
     * @param string $code
     * @return AccessToken
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     */
    private function exchangeAuthorizationCodeForAccessToken(string $code): AccessToken
    {
        // convert auth code to access token, checking PKCE code along the way
        /** @phpstan-ignore-next-line - setPkceCode returns the same object buildOidcProvider builds */
        return $this->buildOidcProvider()
            ->setPkceCode($this->session->get(self::SESSION_PKCE_CODE_KEY))
            ->getAccessToken('authorization_code', ['code' => $code]);
    }

    /**
     * Retrieves the content of the JSON web key set for this instance's
     * configured oAuth provider.
     *
     * @return array
     * @throws ClientExceptionInterface
     */
    private function retrieveJsonWebKeySet(): array
    {
        $config         = $this->getConfigLoader();
        $oauthIssuerUri = new Uri(
            $config->get(ConfigMapper::NAME_OAUTH_ISSUER)
                ?: $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
        );

        $oidcCache = new CacheHelper($this->httpClient, $this->cache);
        try {
            return $oidcCache->retrieveJsonWebKeySet($oauthIssuerUri);
        } catch (InvalidArgumentException $e) {
            throw new SchoolboxException('Could not cache OIDC public key', 0, $e, 500);
        }
    }

    /**
     * Assert that nonce value from ID token matches what's in the session.
     *
     * @param string|null $nonce
     * @return bool
     * @throws ControllerException
     */
    private function verifyNonce(?string $nonce): bool
    {
        // check nonce in ID token
        // TODO this should be done in $loginUri, but that's too disruptive
        //      a change right now
        $sessionNonce = $this->session->get(self::SESSION_NONCE_KEY);
        $this->session->set(self::SESSION_NONCE_KEY, null);
        if (!isset($nonce, $sessionNonce) || $nonce !== $sessionNonce) {
            return false;
        }
        return true;
    }

    /**
     * @param string $requestState
     * @param string $decodedAccessToken
     * @return void
     * @throws RouteNotFound
     */
    private function redirectToSessionInit(string $requestState, string $idToken): void
    {
        $decodedState = $this->codec->decode($requestState);
        $loginUri     = (new Uri())
            ->withPath($this->routeGenerator->generate('api.initSessionFromQueryString'))
            ->withQuery(
                http_build_query([
                    'jwt'      => $idToken,
                    'redirect' => $decodedState['redirect'] ?? '/',
                ])
            );
        $this->response = $this->redirectTemporary($loginUri->__toString());
    }

    /**
     * @param mixed $requestState
     * @return bool
     */
    private function verifyState(string $requestState): bool
    {
        $sessionState = $this->session->get(self::SESSION_STATE_KEY);
        $this->session->set(self::SESSION_STATE_KEY, null);
        if (!isset($requestState, $sessionState) || $requestState !== $sessionState) {
            return false;
        }
        return true;
    }
}
