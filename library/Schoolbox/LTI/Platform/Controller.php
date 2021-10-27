<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3DeepLinking\Factory\ResourceCollectionFactory;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchRequestBuilder;
use OAT\Library\Lti1p3DeepLinking\Settings\DeepLinkingSettings;
use Schoolbox\Instance;
use Schoolbox\LTI\Prototype\Platform\ToolOriginatingLaunchFactory;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Registration\Repository;
use Schoolbox\LTI\Util\OidcAuthenticationRequestFactory;
use Schoolbox\LTI\Util\PlatformInstanceClaimFactory;
use Schoolbox\Url\Factory;

/**
 * Controller for LTI platform stuff.
 */
class Controller extends Base
{
    /**
     * Repository of tool+platform configurations.
     */
    private Repository $repository;

    /**
     * User authenticator.
     */
    private UserAuthenticatorInterface $userAuthenticator;

    /**
     * Instance data repository
     */
    private Instance $instance;

    /**
     * Constructor
     *
     * @param Repository                 $repository
     * @param UserAuthenticatorInterface $userAuthenticator
     * @param Instance                   $instance
     */
    public function __construct(
        Repository $repository,
        UserAuthenticatorInterface $userAuthenticator,
        Instance $instance
    ) {
        $this->repository        = $repository;
        $this->userAuthenticator = $userAuthenticator;
        $this->instance          = $instance;
    }

    /**
     * Generate a deep linking request, which is then sent to the LTI tool.
     *
     * @api GET /lti-platform/deep-linking-request/{toolId}
     * @since 21.1.0
     *
     * @param ToolModel $tool
     * @throws LtiExceptionInterface if the linking request couldn't be built
     * @throws RouteNotFound         if somehow the route was not found
     */
    public function getGenerateDeepLinkingRequest(ToolModel $tool): void
    {
        // Find the tool in the list of registrations
        $repository = $this->repository->find($tool->getDeploymentId());

        // Return URL:
        // this is somewhere else
        $baseUrl   = (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https');
        $returnUrl = (clone $baseUrl)
            ->setPath(
                $this->router->generate('ltiPlatform.getReceiveDeepLinkingResponse', ['toolId' => $tool->getId()])
            )
            ->toString();

        // Standard deep linking settings
        $deepLinkingSettings = new DeepLinkingSettings(
            $returnUrl,
            [LtiResourceLinkInterface::TYPE,],
            ['window', 'iframe'],
            null,
            false,
            false,
        );

        // Figure out who's trying to log in: use that to determine their role
        // TODO make this dependent on the context the request is happening within, or pass via query string
        $role = $this->getUser()->getRole()->isStaff()
            ? RoleInterface::NAMESPACE_CONTEXT . '#Instructor'
            : RoleInterface::NAMESPACE_CONTEXT . '#Learner'
        ;

        // Claim about the platform instance
        $platformInstanceClaim = (new PlatformInstanceClaimFactory($this->instance))->build();

        $builder = new DeepLinkingLaunchRequestBuilder();
        $message = $builder->buildDeepLinkingLaunchRequest(
            $deepLinkingSettings,
            $repository,
            $this->getUser()->getUsername(),
            null,
            null,
            [$role],
            [$platformInstanceClaim],
        );

        // Build a DL request to this tool
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/messageForm.twig',
                ['message' => $message],
            )
        );
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
            $this->repository,
            $this->userAuthenticator,
        ))->build($this->request);

        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/messageForm.twig',
                ['message' => $message,]
            )
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
        $result = (new ToolOriginatingLaunchFactory($this->repository))->build($this->request);
        if ($result->hasError()) {
            throw new LtiException($result->getError());
        }

        // Each content item should be a link, which may be launched as a
        // resource link launch request.
        $contentItems  = $result->getPayload()->getDeepLinkingContentItems();
        $resourceLinks = (new ResourceCollectionFactory())->createFromClaim($contentItems)->normalize();

        // The end recipient of the resource link will need to know about the
        // tool which created the link, so it can launch the link later.
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/receiveDeepLinkingResponse.twig',
                [
                    'tool'          => $tool,
                    'resourceLinks' => $resourceLinks,
                ],
            )
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
