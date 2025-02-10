<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Firebase\JWT\Key;
use League\OAuth2\Server;
use OAT\Library\Lti1p3Core\Security\Jwks\Exporter\JwksExporter;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use OpenIDConnectServer\ClaimExtractor;
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\LTI\KeyChain\RepositoryFactory;
use Schoolbox\Url\Factory;
use Symfony\Component\HttpFoundation\Response;

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
     * @param Server\AuthorizationServer      $authServer                   The authorization server for servicing OIDC
     *                                                                      auth requests.
     * @param KeyChainRepositoryInterface     $keyChains                    Repository of keychains: set up for LTI, but
     *                                                                      usable outside of that context.
     * @param IParser                         $jwtParser                    JWT parser, for extracting a user out of an
     *                                                                      access token.
     * @param ClaimExtractor                  $claimExtractor               Claim extractor: used to filter claims shown
     *                                                                      in userinfo.
     * @param RelyingParty\Mapper             $relyingPartyMapper           Mapper for loading relying parties from the
     *                                                                      auth request.
     * @param RelyingParty\Grant\Mapper       $relyingPartyGrantMapper      Mapper for saving that a user has granted
     *                                                                      access to a relying party.
     * @param RelyingParty\Grant\Scope\Mapper $relyingPartyGrantScopeMapper Mapper for saving the scopes a user has
     *                                                                      granted to a relying party.
     */
    public function __construct(
        private Server\AuthorizationServer $authServer,
        private KeyChainRepositoryInterface $keyChains,
        private IParser $jwtParser,
        private ClaimExtractor $claimExtractor,
        private RelyingParty\Mapper $relyingPartyMapper,
        private RelyingParty\Grant\Mapper $relyingPartyGrantMapper,
        private RelyingParty\Grant\Scope\Mapper $relyingPartyGrantScopeMapper,
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
        $authRequest->setUser(
            new Entity\User($this->getUser(), (new Factory($this->getConfigLoader()))->buildFullyQualified()),
        );

        // Load the relying party and the auth for the current user.
        // If it's accepted already, skip asking for permission.
        $relyingParty      = $this->relyingPartyMapper->findByClientId($authRequest->getClient()->getIdentifier());
        $relyingPartyGrant = $this->relyingPartyGrantMapper->findByRelyingPartyAndUser($relyingParty, $this->getUser());
        $requestedScopes   = $this->convertAuthRequestScopesToCollection($authRequest);
        $grantExpired      = $relyingPartyGrant && $relyingPartyGrant->isExpired(Alaress_Date_Format::now());
        $scopeChanged      = $relyingPartyGrant
            && $requestedScopes->diffBy($relyingPartyGrant->getScopes(), 'scope')->count() > 0;

        // If permission was not passed in the request:
        // * if new scopes have been requested, re-ask for permission
        // * if granted permission has expired, re-ask for permission
        // * otherwise, don't ask if permission already granted
        $permission = $this->requestPost->getBool('permission');
        if (!isset($permission)) {
            if ($relyingPartyGrant) {
                if ($scopeChanged) {
                    $permission = null;
                } elseif (!$grantExpired) {
                    $permission = true;
                }
            }
        }

        // Display confirm auth page:
        // this should be bypassed if the user has already given permission
        // for this client.
        // (but in this case, the access token will probably just be used)
        if (!isset($permission)) {
            $this->response = $this->responseFromTemplateUnthemed(
                '@oidcServer/authorization.twig',
                [
                    'authorizationUrl'  => $this->request->getUri(),
                    'authRequest'       => $authRequest,
                    // Separate variable, as this isn't a standard scope name.
                    'schoolboxScope'    => Repository\Scope::SCOPE_SCHOOLBOX,
                    'relyingPartyGrant' => $relyingPartyGrant,
                ],
            );
        } else {
            $authRequest->setAuthorizationApproved($permission);

            // At this point, if permission was granted: track that the server
            // has granted a session to the client.
            // Currently, only the client can be tracked, not a particular
            // session ID (as that doesn't exist in the auth code, and I can't
            // easily add it).
            if ($permission) {
                // Save user's grant of permission
                $updateRelyingPartyGrant = false;
                if (!$relyingPartyGrant) {
                    $relyingPartyGrant = $this->relyingPartyGrantMapper->createModel()
                        ->setUser($this->getUser())
                        ->setRelyingParty($relyingParty)
                        ->setScopes($this->relyingPartyGrantScopeMapper->createCollection());
                    $updateRelyingPartyGrant = true;
                } elseif ($scopeChanged || $grantExpired) {
                    $updateRelyingPartyGrant = true;
                }
                if ($updateRelyingPartyGrant) {
                    // Set expiry and scopes
                    $relyingPartyGrant = $relyingPartyGrant
                        ->setExpiresAt(Alaress_Date_Format::now()->modify(RelyingParty\Grant\Model::DEFAULT_EXPIRY))
                        ->setScopes($requestedScopes);
                    $this->relyingPartyGrantMapper->save($relyingPartyGrant);
                }
            }

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
        try {
            $this->response = $this->authServer->respondToAccessTokenRequest(
                $this->request,
                $this->responseEmpty(),
            );
        } catch (Server\Exception\OAuthServerException $e) {
            $this->response = $e->generateHttpResponse($this->responseEmpty());
        }
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
            'userinfo_endpoint'                     => $baseUrl->withPath($this->routeGenerator->generate('oidcServer.getUserinfo')),
            'jwks_uri'                              => $baseUrl->withPath($this->routeGenerator->generate('oidcServer.getJsonWebKeySet')),
            'scopes_supported'                      => Repository\Scope::AVAILABLE_SCOPES,
            'response_types_supported'              => ['code', 'id_token', 'id_token token'],
            'subject_types_supported'               => ['public'],
            'id_token_signing_alg_values_supported' => [Jwt::SIGNING_ALGORITHM],

            // Allegedly OIDC must support the implicit grant type, but it is deprecated?
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'implicit', 'client_credentials'],

            // Schoolbox now supports back-channel logout:
            // it currently does not support sessions for back-channel logout,
            // therefore logging out of the provider will log out any sessions
            // for any relying parties.
            'backchannel_logout_supported'         => true,
            'backchannel_logout_session_supported' => false,
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

    /**
     * Userinfo endpoint: get an identity for the user from the access token.
     *
     * @see https://openid.net/specs/openid-connect-core-1_0.html#UserInfo
     *
     * @api   GET /oidc/userinfo
     * @since 24.0
     */
    public function getUserinfo(): void
    {
        $authHeader = $this->request->getHeaderLine('authorization');
        preg_match('/^Bearer\\s+(.*)/i', $authHeader, $matches);
        if (!$matches) {
            $this->sendJsonError('Access token must be provided as a Bearer token', Response::HTTP_BAD_REQUEST);
            return;
        }
        [, $accessToken] = $matches;

        // Extract the user out of the access token
        $issuerUrl  = (new Factory($this->getConfigLoader()))->buildFullyQualified();
        $user       = $this->jwtParser->determineUser($accessToken);
        $userEntity = new Entity\User($user, $issuerUrl);

        // Actually decode the access token,
        // to determine which scopes the access token allows
        $decodedJwt = $this->jwtParser->decode(
            $accessToken,
            new Key($this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY), Jwt::SIGNING_ALGORITHM),
            (int)$this->getConfigLoader()->get(ConfigMapper::NAME_JWT_LEEWAY),
        );

        // Create a response for the user identified by the access token,
        // with the claim set allowed in the access token's scopes.
        $idTokenClaims = $this->claimExtractor->extract($decodedJwt['scopes'] ?? [], $userEntity->getClaims());

        // Also include the "sub" claim if it's not in the ID token.
        $idTokenClaims[IParser::CLAIM_SUB] ??= $user->getUsername();

        // What's the response type?
        // application/json?
        // application/jwt? return a JWT
        $acceptedResponseType = $this->request->getHeaderLine('Accept');
        if (strtolower($acceptedResponseType) === 'application/jwt') {
            // Generate a JWT
            $idTokenClaims[IParser::CLAIM_ISS] = $issuerUrl->__toString();
            $idTokenClaims[IParser::CLAIM_AUD] = $decodedJwt[IParser::CLAIM_AUD];
            $this->response = $this->responseFromContent(
                \Firebase\JWT\JWT::encode(
                    $idTokenClaims,
                    $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
                    Jwt::SIGNING_ALGORITHM,
                    Jwt::KEY_ID,
                )
            )->withHeader('Content-Type', 'application/jwt');
        } else {
            $this->response = $this->responseAsJson($idTokenClaims);
        }
    }

    /**
     * Convert the scopes from an auth request into a set of scopes for the
     * granted permission.
     *
     * @param Server\RequestTypes\AuthorizationRequest $authRequest
     * @return GenericCollection<RelyingParty\Grant\Scope\Model>
     */
    private function convertAuthRequestScopesToCollection(
        Server\RequestTypes\AuthorizationRequest $authRequest,
    ): GenericCollection {
        $newScopes = $this->relyingPartyGrantScopeMapper->createCollection();
        foreach ($authRequest->getScopes() as $scope) {
            $newScopes->add(
                $this->relyingPartyGrantScopeMapper->createModel()
                    ->setScope($scope->getIdentifier())
            );
        }
        return $newScopes;
    }
}
