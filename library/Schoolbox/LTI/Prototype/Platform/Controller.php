<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Platform;

use Alaress\Web\Controller\Base;
use Schoolbox\LTI\Platform\ToolOriginatingLaunchFactory;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3DeepLinking\Factory\ResourceCollectionFactory;
use Schoolbox\LTI\Prototype\RegistrationRepository;
use Schoolbox\LTI\Prototype\RegistrationRepositoryFactory;
use Schoolbox\LTI\Prototype\UserAuthenticator;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\LTI\Util\OidcAuthenticationRequestFactory;
use Schoolbox\Role;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * LTI prototype platform controller
 */
class Controller extends Base
{
    /**
     * Default title for this controller's pages.
     */
    private const TITLE = 'LTI Prototype Platform';

    /**
     * User mapper, required for authenticating the user who is passed in the
     * resource link request (this isn't always the logged-in user)
     */
    private UserMapper $userMapper;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Index for prototype platform: displays a form which contains various
     * claims which may be added to the resource link request.
     *
     * @api GET /lti-prototype/platform
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        [$staffUsers, $studentUsers] = $this->buildUserLists();
        $this->response = $this->responseFromTemplate(
            '@ltiPrototypePlatform/index.twig',
            [
                'staffUsers'    => $staffUsers,
                'studentUsers'  => $studentUsers,
                'registrations' => $this->buildRegistrationRepository()->findAll(),
                'messageTypes'  => [
                    LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST => 'Resource Link',
                    LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_REQUEST  => 'Deep Linking',
                ],
            ],
            self::TITLE,
        );
    }

    /**
     * Generate a request, based upon the form values passed.
     *
     * @api GET /lti-prototype/platform/generate-message
     * @since 21.1.0
     */
    public function getGenerateMessage(): void
    {
        // Build the registration repository, then get a registration out of it:
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/interfaces/#registration-repository-interface
        $registration = $this->buildRegistrationRepository()->find($this->requestQuery->get('registration'));
        if (!$registration) {
            $this->sendError('Registration not found');
            return;
        }

        // I have a registration: I may now create a message
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#create-the-message
        $message = (new MessageFactory($this->userMapper, $registration))
            ->build(
                $this->requestQuery->get('loginHint'),
                $this->requestQuery->get('messageType'),
                $this->requestQuery->get('targetUrl'),
                (string)(new Factory($this->getConfigLoader()))
                    ->buildFullyQualified()
                    ->withScheme('https')
                    ->withPath($this->routeGenerator->generate('ltiPrototypePlatform.getDeepLinkingResponse')),
            );

        $this->response = $this->responseFromTemplate(
            '@ltiPrototypePlatform/generateMessage.twig',
            ['message' => $message,],
            self::TITLE,
        );
    }

    /**
     * Authenticate an OIDC request
     *
     * @api GET /lti-prototype/platform/oidc/authentication
     * @since 21.1.0
     */
    public function getOidcAuthenticationRequest(): void
    {
        // Do something with the OIDC request when I receive it
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#3-platform-side-oidc-authentication
        try {
            $message = (new OidcAuthenticationRequestFactory(
                $this->buildRegistrationRepository(),
                new UserAuthenticator($this->userMapper)
            ))->build($this->request);
            $this->response = $this->responseFromTemplate(
                '@ltiPrototypePlatform/oidcAuthenticationRequest.twig',
                ['message' => $message,]
            );
        } catch (LtiExceptionInterface $e) {
            $this->response = $this->responseFromTemplate(
                '@ltiPrototypePlatform/oidcAuthenticationRequestException.twig',
                ['exception' => $e,],
                self::TITLE,
            );
        }
    }

    /**
     * Handle a deep linking request response from the tool.
     *
     * @api GET /lti-prototype/platform/deep-linking-response
     * @since 21.1.0
     */
    public function getDeepLinkingResponse(): void
    {
        // Validate launch
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/tool-originating-messages/#2-platform-side-launch-validation
        $result = (new ToolOriginatingLaunchFactory($this->buildRegistrationRepository()))->build($this->request);

        // Each content item should be a link, which may be launched as a resource link launch request.
        $contentItems  = $result->getPayload()->getDeepLinkingContentItems();
        $resourceLinks = (new ResourceCollectionFactory())->createFromClaim($contentItems);

        [$staffUsers, $studentUsers] = $this->buildUserLists();
        $this->response = $this->responseFromTemplate(
            '@ltiPrototypePlatform/deepLinkingResponse.twig',
            [
                'result'        => $result,
                'staffUsers'    => $staffUsers,
                'studentUsers'  => $studentUsers,
                'resourceLinks' => $resourceLinks,
                'messageType'   => LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            ],
        );
    }

    /**
     * Handle a deep linking request response from the tool.
     *
     * @api POST /lti-prototype/platform/deep-linking-response
     * @since 21.1.0
     */
    public function postDeepLinkingResponse(): void
    {
        $this->getDeepLinkingResponse();
    }

    /**
     * Build the registration repository for this platform.
     *
     * @return RegistrationRepository
     */
    private function buildRegistrationRepository(): RegistrationRepository
    {
        return (new RegistrationRepositoryFactory($this->getConfigLoader(), ToolMapper::getInstance()))->build();
    }

    /**
     * Build lists for the user selector dropdowns.
     *
     * @return array
     */
    private function buildUserLists(): array
    {
        $excludeCurrentUser = function (User $user) {
            return $this->getUser()->getId() !== $user->getId();
        };
        $staffUsers   = $this->userMapper->findByColumn('g.group_type', Role::STAFF)
                                         ->filter($excludeCurrentUser)
                                         ->sortNaturally('username')
                                         ->slice(0, 5);
        $studentUsers = $this->userMapper->findByColumn('g.group_type', Role::STUDENT)
                                         ->filter($excludeCurrentUser)
                                         ->sortNaturally('username')
                                         ->slice(0, 5);
        return [$staffUsers, $studentUsers];
    }
}
