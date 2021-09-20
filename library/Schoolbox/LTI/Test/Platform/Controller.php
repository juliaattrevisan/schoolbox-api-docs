<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test\Platform;

use Alaress\Web\Controller\Base;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator;
use Schoolbox\LTI\Test\RegistrationRepository;
use Schoolbox\LTI\Test\RegistrationRepositoryFactory;
use Schoolbox\LTI\Test\ServerRequestFactory;
use Schoolbox\LTI\Test\UserAuthenticator;
use Schoolbox\LTI\Test\UserAuthenticatorFactory;
use Schoolbox\Role;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * LTI test platform controller
 */
class Controller extends Base
{
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
     * Index for test platform: displays a form which contains various claims
     * which may be added to the resource link request.
     *
     * @api GET /lti-test/platform
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        $excludeCurrentUser = function (User $user) {
            return $this->getUser()->getId() !== $user->getId();
        };
        $staffUsers   = $this->userMapper->findByColumn('g.group_type', Role::STAFF)
                                         ->filter($excludeCurrentUser)
                                         ->sortNaturally('fullName')
                                         ->slice(0, 5);
        $studentUsers = $this->userMapper->findByColumn('g.group_type', Role::STUDENT)
                                         ->filter($excludeCurrentUser)
                                         ->sortNaturally('fullName')
                                         ->slice(0, 5);
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/index.twig',
                [
                    'staffUsers'   => $staffUsers,
                    'studentUsers' => $studentUsers,
                ]
            )
        );
    }

    /**
     * Generate a resource link request, based upon the form values passed.
     *
     * @api POST /lti-test/platform/generate-resource-link-request
     * @since 21.1.0
     */
    public function postGenerateResourceLinkRequest(): void
    {
        // Build the registration repository
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/interfaces/#registration-repository-interface
        $registrationRepository = $this->buildRegistrationRepository();

        // Get a registration out of it
        $registration = $registrationRepository->find(RegistrationRepository::REGISTRATION_IDENTIFIER);
        if (!$registration) {
            $this->sendError('Registration not found');
        }

        // Figure out who's trying to log in: use that to determine their role
        $role      = null;
        $loginHint = $this->request->post->get('loginHint');
        if (strlen($loginHint) > 0) {
            $user = $this->userMapper->findByUsername($loginHint);
            if ($user) {
                $role = $user->getRole()->isStaff()
                    ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'
                    : 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
            }
        }
        $role ??= 'http://purl.imsglobal.org/vocab/lis/v2/system/person#None';

        // I have a registration: I may now create a message
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#create-the-message
        $builder = new PlatformOriginatingLaunchBuilder();
        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https');
        $message = $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            (clone $baseUrl)->setPath($this->request->post->get('targetLinkUri'))->toString(),
            $loginHint,
            null,
            [$role],
            [
                new ResourceLinkClaim('test', 'Test resource link'),
            ],
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/generateResourceLinkRequest.twig',
                ['messageUrl' => $message->toUrl(),]
            )
        );
    }

    /**
     * Authenticate an OIDC request
     *
     * @api GET /lti-test/platform/oidc/authentication
     * @since 21.1.0
     */
    public function getOidcAuthenticationRequest(): void
    {
        // Do something with the OIDC request when I receive it
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#3-platform-side-oidc-authentication
        $oidcAuthenticator = new OidcAuthenticator(
            $this->buildRegistrationRepository(),
            new UserAuthenticator($this->userMapper),
        );
        $serverRequest = (new ServerRequestFactory())->build($this->request);
        try {
            $message = $oidcAuthenticator->authenticate($serverRequest);
            $this->response->content->set(
                $this->buildHtmlOutput(
                    '@ltiTestPlatform/oidcAuthenticationRequest.twig',
                    ['messageUrl' => $message->toUrl(),]
                )
            );
        } catch (LtiExceptionInterface $e) {
            $this->response->content->set(
                $this->buildHtmlOutput(
                    '@ltiTestPlatform/oidcAuthenticationRequestException.twig',
                    ['exception' => $e,]
                )
            );
        }
    }

    /**
     * Build the test registration repository for this platform.
     *
     * @return RegistrationRepository
     */
    private function buildRegistrationRepository(): RegistrationRepository
    {
        return (new RegistrationRepositoryFactory($this->getConfigLoader()))->build();
    }
}
