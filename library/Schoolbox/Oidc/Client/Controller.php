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
use GuzzleHttp\RequestOptions;
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
     * Depending on the type of response the auth request sends, the OIDC server
     * may respond with any of the following:
     * * an ID token: send it directly to the init session endpoint
     * * an access token: exchange for an ID token via the server's userinfo
     *   endpoint, then send the ID token to the init session endpoint
     * * an auth code: exchange the auth code for an access token + ID token,
     *   then send the ID token to the init session endpoint
     *
     * All of the above cases check the request's state and the ID token's nonce
     * value before forwarding the token.
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
        $idToken      = $this->requestPost->get('id_token') ?? $this->requestQuery->get('id_token');
        $requestState = $this->requestPost->get('state') ?? $this->requestQuery->get('state');
        $cookieStore  = new CookieStore();
        if (isset($idToken)) {
            $cookieState = $cookieStore->getState($this->request);
            $cookieNonce = $cookieStore->getNonce($this->request);
            $cookieStore->clearAll();
            if (!$this->verifyState($requestState, $cookieState)) {
                return;
            }
            $decodedIdToken = $this->decodeAccessToken($idToken);
            if (!$this->verifyNonce($decodedIdToken['nonce'] ?? null, $cookieNonce)) {
                return;
            }
            $this->redirectToSessionInit($requestState, $idToken);
            return;
        }

        $accessToken = $this->requestPost->get('access_token') ?? $this->requestQuery->get('access_token');
        if (isset($accessToken)) {
            $cookieState = $cookieStore->getState($this->request);
            $cookieStore->clearAll();
            if (!$this->verifyState($requestState, $cookieState)) {
                return;
            }

            // Get an ID token as a JWT using the userinfo endpoint
            $oidcConfig      = $this->retrieveOidcConfig();
            $idTokenResponse = $this->httpClient->get(
                $oidcConfig['userinfo_endpoint'],
                [
                    RequestOptions::HEADERS => [
                        'accept'        => 'application/jwt',
                        'authorization' => 'Bearer ' . $accessToken,
                    ]
                ]
            );
            $idToken = $idTokenResponse->getBody()->getContents();

            // Cannot check nonce, as it won't exist in a userinfo JWT
            $this->redirectToSessionInit($requestState, $idToken);
            return;
        }

        $code = $this->requestPost->get('code') ?? $this->requestQuery->get('code');
        if (isset($code)) {
            $cookieState    = $cookieStore->getState($this->request);
            $cookiePkceCode = $cookieStore->getPkceCode($this->request);
            $cookieNonce    = $cookieStore->getNonce($this->request);
            $cookieStore->clearAll();
            if (!$this->verifyState($requestState, $cookieState)) {
                return;
            }

            // Exchange auth code for access token
            $decodedAccessToken = $this->exchangeAuthorizationCodeForAccessToken($code, $cookiePkceCode);
            if (!$this->verifyNonce($decodedAccessToken->getIdToken()->claims()->get('nonce'), $cookieNonce)) {
                return;
            }
            $this->redirectToSessionInit($requestState, $decodedAccessToken->getIdToken()->toString());
            return;
        }

        // No ID token, access token or auth code? Send an auth request
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

        // Get the state and nonce generated for you and store them in a cookie
        $cookieStore = new CookieStore();
        $cookieStore->set($oidcProvider->getState(), $oidcProvider->getPkceCode(), $nonce);

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
        $oidcConfig = $this->retrieveOidcConfig();

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
        return (array)(Jwt::decode($token, $keySet));
    }

    /**
     * Exchange a given auth code for an access token.
     *
     * @param string      $code
     * @param string|null $pkceCode
     * @return AccessToken
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws JWKToPEM\Exception\Base64DecodeException
     * @throws JWKToPEM\Exception\JWKConverterException
     * @throws RouteNotFound
     */
    private function exchangeAuthorizationCodeForAccessToken(string $code, ?string $pkceCode): AccessToken
    {
        // convert auth code to access token, checking PKCE code along the way
        /** @phpstan-ignore-next-line - setPkceCode returns the same object buildOidcProvider builds */
        return $this->buildOidcProvider()
            ->setPkceCode($pkceCode)
            ->getAccessToken('authorization_code', ['code' => $code]);
    }

    /**
     * Retrieves the content of the well-known configuration for this instance's
     * configured oAuth provider.
     *
     * @return array
     * @throws ClientExceptionInterface
     */
    private function retrieveOidcConfig(): array
    {
        $config         = $this->getConfigLoader();
        $oauthIssuerUri = new Uri(
            $config->get(ConfigMapper::NAME_OAUTH_ISSUER)
                ?: $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)
        );

        $oidcCache = new CacheHelper($this->httpClient, $this->cache);
        try {
            $oidcConfig = $oidcCache->retrieveOidcConfig($oauthIssuerUri);
        } catch (InvalidArgumentException $e) {
            throw new SchoolboxException('Could not cache OIDC configuration or public key', 0, $e, 500);
        }
        return $oidcConfig;
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
     * Verify that the state value from the request matches what's in the
     * session.
     *
     * @param string|null $requestState
     * @param string|null $cookieState
     * @return bool
     */
    private function verifyState(?string $requestState, ?string $cookieState): bool
    {
        if (!isset($requestState, $cookieState) || $requestState !== $cookieState) {
            $this->sendError('State sent to and received from OIDC provider do not match');
            return false;
        }
        return true;
    }

    /**
     * Verify that the nonce value from ID token matches what's in the session.
     *
     * @param string|null $tokenNonce
     * @param string|null $cookieNonce
     * @return bool
     */
    private function verifyNonce(?string $tokenNonce, ?string $cookieNonce): bool
    {
        // check nonce in ID token
        // TODO this should be done in $loginUri, but that's too disruptive
        //      a change right now
        if (!isset($tokenNonce, $cookieNonce) || $tokenNonce !== $cookieNonce) {
            $this->sendError('Nonce sent to and received from OIDC provider do not match');
            return false;
        }
        return true;
    }

    /**
     * Create a redirect response sending the user to the init session URL.
     *
     * @param string $requestState The encoded state of the request: may contain a URL to redirect to after session init
     * @param string $idToken      The ID token to forward to the init session URL.
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
}
