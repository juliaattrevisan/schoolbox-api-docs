<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Web\Controller\Base;
use League\OAuth2\Server;
use OAT\Library\Lti1p3Core\Security\Jwks\Exporter\JwksExporter;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\NotAuthenticated;
use Schoolbox\LTI\KeyChain\RepositoryFactory;
use Schoolbox\Oidc\Server\RelyingParty\Mapper;
use Schoolbox\Url\Factory;

/**
 * Controller for OpenID Connect identity provision.
 *
 * This implements Schoolbox as an identity provider (e.g. a server) for an
 * OpenID relying party.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Server\AuthorizationServer  $authServer The authorization server used to service OIDC auth requests.
     * @param KeyChainRepositoryInterface $keyChains  Repository of keychains: set up for LTI, but usable outside of
     *                                                that context.
     */
    public function __construct(
        private Server\AuthorizationServer $authServer,
        private KeyChainRepositoryInterface $keyChains,
    ) {
    }

    /**
     * Render an authorization page for an OIDC authorization code flow.
     *
     * This displays a form which queries whether the user authorizes the
     * relying party to access the data it requests.
     *
     * @api GET /oidc/authorize
     * @since 24.0
     *
     * @return void
     */
    public function getAuthorization(): void
    {
        $authRequest = $this->authServer->validateAuthorizationRequest($this->request);

        // There needs to be a currently authenticated user:
        // If nobody's logged in, and logging in with client credentials:
        // auth as whoever's configured, otherwise return 403.
        // TODO move to permissions?
        $user = $this->getUser();
        if (!$user->getId()) {
            throw new NotAuthenticated();
        }
        $authRequest->setUser(new Entity\User($user, (new Factory($this->getConfigLoader()))->buildFullyQualified()));

        // Display confirm auth page:
        // this should be bypassed if the user has already given permission
        // for this client.
        // (but in this case, the access token will probably just be used)
        $permission = $this->requestPost->getBool('permission');
        if (!isset($permission)) {
            $this->response = $this->responseFromTemplateUnthemed(
                '@oidcServer/authorization.twig',
                [
                    'authorizationUrl' => $this->request->getUri(),
                    'authRequest'      => $authRequest,
                    // Separate variable, as this isn't a standard scope name.
                    'schoolboxScope'   => Repository\Scope::SCOPE_SCHOOLBOX,
                ],
            );
        } else {
            $authRequest->setAuthorizationApproved($permission);
            try {
                $this->response = $this->authServer->completeAuthorizationRequest($authRequest, $this->responseEmpty());
            } catch (Server\Exception\OAuthServerException $e) {
                $this->response = $e->generateHttpResponse($this->responseEmpty());
            }
        }
    }

    /**
     * POST request for receiving an authorization code.
     *
     * This is the only way in which the auth request may be confirmed or
     * rejected.
     *
     * @api   POST /oidc/authorize
     * @since 24.0
     *
     * @see getAuthorization
     */
    public function postAuthorization(): void
    {
        $this->getAuthorization();
    }

    /**
     * Exchange a received auth code for an access token.
     *
     * @api   GET /oidc/token
     * @since 24.0
     *
     * @return void
     */
    public function getAccessTokenRequest(): void
    {
        $this->response = $this->authServer->respondToAccessTokenRequest(
            $this->request,
            $this->responseEmpty(),
        );
    }

    /**
     * @see getAccessToken
     */
    public function postAccessTokenRequest(): void
    {
        $this->getAccessTokenRequest();
    }

    /**
     * OpenID configuration.
     *
     * @see https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderMetadata
     *
     * @api   GET /.well-known/openid-configuration
     * @since 24.0
     */
    public function getOpenIdConfiguration(): void
    {
        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified();

        // Minimum set of required content for OpenID configuration.
        $this->response = $this->responseAsJson([
            'issuer'                                => $baseUrl,
            'authorization_endpoint'                => $baseUrl->withPath($this->routeGenerator->generate('oidcServer.getAuthorization')),
            'token_endpoint'                        => $baseUrl->withPath($this->routeGenerator->generate('oidcServer.getAccessTokenRequest')),
            'jwks_uri'                              => $baseUrl->withPath($this->routeGenerator->generate('oidcServer.getJsonWebKeySet')),
            'scopes_supported'                      => Repository\Scope::AVAILABLE_SCOPES,
            'response_types_supported'              => ['code', 'id_token', 'id_token token'],
            'subject_types_supported'               => ['public'],
            'id_token_signing_alg_values_supported' => [Jwt::SIGNING_ALGORITHM],

            // Allegedly OIDC must support the implicit grant type, but it is deprecated?
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'implicit', 'client_credentials'],
        ]);
    }

    /**
     * Generate a JSON Web Key Set (JWKS).
     *
     * Copied from `\Schoolbox\LTI\Platform\Controller::getJsonWebKeySet`:
     * however, this is in a more generic location.
     *
     * @api   GET /.well-known/jwks.json
     * @since 24.0
     */
    public function getJsonWebKeySet(): void
    {
        $jwksExporter   = new JwksExporter($this->keyChains);
        $this->response = $this->responseAsJson(
            $jwksExporter->export(RepositoryFactory::PLATFORM_KEYCHAIN_KEY_SET_NAME),
        );
    }
}
