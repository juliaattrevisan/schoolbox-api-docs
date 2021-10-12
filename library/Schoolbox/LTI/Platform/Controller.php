<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Web\Controller\Base;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\PlatformInstanceClaim;
use OAT\Library\Lti1p3Core\Resource\Link\LinkInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchRequestBuilder;
use OAT\Library\Lti1p3DeepLinking\Settings\DeepLinkingSettings;
use Schoolbox\LTI\Auth\UserAuthenticator;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Registration\Repository;
use Schoolbox\LTI\Util\OidcAuthenticationRequestFactory;
use Schoolbox\Url\Factory;
use Schoolbox\UserMapper;

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
     * Constructor
     *
     * @param Repository                 $repository
     * @param UserAuthenticatorInterface $userAuthenticator
     */
    public function __construct(Repository $repository, UserAuthenticatorInterface $userAuthenticator)
    {
        $this->repository = $repository;
        $this->userAuthenticator = $userAuthenticator;
    }

    /**
     * Test endpoint for posting _something_ back to the window which opened
     * this iframe.
     *
     * @api GET /lti-platform/
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        // Test posting a message back to the form in an iframe
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/index.twig',
                [],
            )
        );
    }

    /**
     * Generate a deep linking request, which is then sent to the LTI tool.
     *
     * @api GET /lti-platform/deep-linking-request/{toolId}
     * @since 21.1.0
     */
    public function getGenerateDeepLinkingRequest(ToolModel $tool): void
    {
        // Find the tool in the list of registrations
        $repository = $this->repository->find($tool->getDeploymentId());

        // Return URL:
        // this is somewhere else
        $baseUrl   = (new Factory($this->getConfigLoader()))->buildFullyQualified();
        $returnUrl = (clone $baseUrl)
            ->setPath($this->router->generate('ltiPlatform.getReceiveDeepLinkingResponse'))->toString();

        // Standard deep linking settings
        $deepLinkingSettings = new DeepLinkingSettings(
            $returnUrl,
            [LtiResourceLinkInterface::TYPE, LinkInterface::TYPE,],
            ['window', 'iframe'],
            null,
            false,
            false,
        );

        // Figure out who's trying to log in: use that to determine their role
        $role = $this->getUser()->getRole()->isStaff()
            ? RoleInterface::NAMESPACE_CONTEXT . 'Instructor'
            : RoleInterface::NAMESPACE_CONTEXT . 'Learner'
        ;

        // Claim about the platform instance
        $platformInstanceClaim = new PlatformInstanceClaim(
            INSTANCE_ID,
            null,
            null,
            null,
            null,
            'schoolbox',
        );

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
     * @api GET /lti-platform/oidc/authentication
     * @since 21.1.0
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
            $this->buildHtmlOutput(
                '@ltiPlatform/messageForm.twig',
                ['message' => $message,]
            )
        );
    }

    public function getReceiveDeepLinkingResponse(): void
    {
        // Send a message to the top-level window, to update the:
        // * tool's identifier
        // * URL
        // * custom fields
        // * line item, if provided
    }

    public function postReceiveDeepLinkingResponse(): void
    {
        $this->getReceiveDeepLinkingResponse();
    }
}
