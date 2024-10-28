<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Web\Controller\Base;
use GuzzleHttp\Psr7\HttpFactory;
use OAT\Library\Lti1p3Ags\Service\Score\ScoreServiceInterface;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwks\Exporter\JwksExporter;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\Scope;
use OAT\Library\Lti1p3Core\Security\OAuth2\Factory\AuthorizationServerFactory;
use OAT\Library\Lti1p3Core\Security\OAuth2\Generator\AccessTokenResponseGenerator;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\ClientRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\ScopeRepository;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3DeepLinking\Factory\ResourceCollectionFactory;
use OAT\Library\Lti1p3Nrps\Service\MembershipServiceInterface;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\LTI\KeyChain\RepositoryFactory as KeyChainRepositoryFactory;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Util\OidcAuthenticationRequestFactory;

/**
 * Controller for LTI platform stuff.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param KeyChainRepositoryInterface     $keyChains         Repository of keychains.
     * @param RegistrationRepositoryInterface $registrations     Repository of tool+platform registrations.
     * @param UserAuthenticatorInterface      $userAuthenticator User authenticator.
     * @param CacheItemPoolInterface          $accessTokenCache  Cache for access tokens.
     * @param ToolMapper                      $toolMapper        Tool mapper, for fetching configuration when
     *                                                           authenticating incoming OIDC requests.
     *
     */
    public function __construct(
        private KeyChainRepositoryInterface $keyChains,
        private RegistrationRepositoryInterface $registrations,
        private UserAuthenticatorInterface $userAuthenticator,
        private CacheItemPoolInterface $accessTokenCache,
        private ToolMapper $toolMapper,
    ) {
    }

    /**
     * Authenticate an OIDC request
     *
     * @api   GET /lti-platform/oidc/authentication
     * @since 21.1.0
     *
     * @throws LtiExceptionInterface if the request could not be built
     */
    public function getOidcAuthenticationRequest(): void
    {
        // Do something with the OIDC request when I receive it
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#3-platform-side-oidc-authentication
        $message = (new OidcAuthenticationRequestFactory(
            $this->registrations,
            $this->userAuthenticator,
            $this->toolMapper,
        ))->build($this->request);

        $this->setAllowBackToTheFuture(false);

        $this->response = $this->responseFromTemplateUnthemed(
            '@ltiPlatform/messageForm.twig',
            ['message' => $message,]
        );
    }

    /**
     * Authenticate an OIDC request
     *
     * @api   POST /lti-platform/oidc/authentication
     * @since 21.1.0
     *
     * @throws LtiExceptionInterface if the request could not be built
     */
    public function postOidcAuthenticationRequest(): void
    {
        $this->getOidcAuthenticationRequest();
    }

    /**
     * Generate oAuth access tokens
     *
     * @api   GET /lti-platform/auth/token
     * @since 21.1.0
     */
    public function getOauthAccessTokenRequest(): void
    {
        $clientRepository = new ClientRepository($this->registrations);
        $factory          = new AuthorizationServerFactory(
            $clientRepository,
            new AccessTokenRepository($this->accessTokenCache),
            new ScopeRepository([
                new Scope(ScoreServiceInterface::AUTHORIZATION_SCOPE_SCORE),
                new Scope(MembershipServiceInterface::AUTHORIZATION_SCOPE_MEMBERSHIP),
            ]),
            // Currently, the auth token gets verified in:
            // `\Schoolbox\Learning\Assessment\Lti\Controller::postScorePublish`
            // OAT-SA docs make no mention of how to actually verify the token.
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
        );

        // Build the access token response
        $generator          = new AccessTokenResponseGenerator($this->keyChains, $factory);
        $this->response = $generator->generate(
            $this->request,
            (new HttpFactory())->createResponse(),
            KeyChainRepositoryFactory::PLATFORM_KEYCHAIN_IDENTIFIER,
        );
    }

    /**
     * Generate oAuth access tokens
     *
     * @api   POST /lti-platform/auth/token
     * @since 21.1.0
     */
    public function postOauthAccessTokenRequest(): void
    {
        $this->getOauthAccessTokenRequest();
    }

    /**
     * Generate a JSON Web Key Set (JWKS)
     *
     * @api   GET /lti-platform/.well-known/jwks.json
     * @since 21.1.0
     */
    public function getJsonWebKeySet(): void
    {
        $jwksExporter   = new JwksExporter($this->keyChains);
        $this->response = $this->responseAsJson($jwksExporter->export(KeyChainRepositoryFactory::PLATFORM_KEYCHAIN_KEY_SET_NAME));
    }

    /**
     * Receive a deep linking response from the LTI tool.
     *
     * Currently, this assumes it's in an iframe: it will send a message to its
     * opening window about the content items which were selected.
     *
     * @api   GET /lti-platform/deep-linking-response/{toolId}
     * @since 21.1.0
     *
     * @param ToolModel $tool
     * @throws LtiExceptionInterface if the result contains an error
     * @throws LtiExceptionInterface if somehow resource links can't be created
     */
    public function getReceiveDeepLinkingResponse(ToolModel $tool): void
    {
        // Validate launch
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/tool-originating-messages/#2-platform-side-launch-validation
        $result = (new ToolOriginatingLaunchFactory($this->registrations))->build($this->request);
        if ($result->hasError()) {
            throw new LtiException($result->getError());
        }

        // Each content item should be a link, which may be launched as a
        // resource link launch request.
        $contentItems  = $result->getPayload()->getDeepLinkingContentItems();
        $resourceLinks = (new ResourceCollectionFactory())->createFromClaim($contentItems)->normalize();

        // The end recipient of the resource link will need to know about the
        // tool which created the link, so it can launch the link later.
        $this->response = $this->responseFromTemplateUnthemed(
            '@ltiPlatform/receiveDeepLinkingResponse.twig',
            [
                'context'       => $this->requestQuery->get('context'),
                'messageType'   => LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_RESPONSE,
                'tool'          => $tool,
                'resourceLinks' => $resourceLinks,
            ],
        );
    }

    /**
     * Receive a deep linking response from the LTI tool.
     *
     * @see   getReceiveDeepLinkingResponse
     * @api   POST /lti-platform/deep-linking-response/{toolId}
     * @since 21.1.0
     *
     * @param ToolModel $tool
     * @throws LtiExceptionInterface if somehow resource links can't be created
     */
    public function postReceiveDeepLinkingResponse(ToolModel $tool): void
    {
        $this->getReceiveDeepLinkingResponse($tool);
    }
}
