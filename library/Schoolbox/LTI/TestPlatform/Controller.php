<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\TestPlatform;

use Alaress\Web\Controller\Base;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResult;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResultInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use Schoolbox\Url\Factory;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Index for test platform.
     *
     * @api GET /lti-test-platform
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        // Build the registration repository
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/interfaces/#registration-repository-interface
        $registrationRepository = new RegistrationRepository(
            (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https')->toString(),
        );

        // Get a registration out of it
        $registration = $registrationRepository->find(RegistrationRepository::REGISTRATION_IDENTIFIER);
        if (!$registration) {
            $this->sendError('Registration not found');
        }

        // I have a registration: I may now create a message
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#create-the-message
        $builder = new PlatformOriginatingLaunchBuilder();
        $message = $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            'http://devkit-lti1p3.localhost/tool/launch',
            'loginHint',
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/index.twig',
                [
                    'exampleToolConfig' => $exampleToolConfig ?? [],
                    'messageUrl'        => $message->toUrl(),
                ]
            )
        );
    }

    /**
     * Authenticate an OIDC request
     */
    public function getOidcAuthenticationRequest(): void
    {
        // Do something with the OIDC request when I receive it
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#3-platform-side-oidc-authentication
        $userAuthenticator = new class ($this->getUser()) implements UserAuthenticatorInterface
        {
            private User $currentUser;

            public function __construct(User $currentUser)
            {
                $this->currentUser = $currentUser;
            }

            public function authenticate(RegistrationInterface $registration, string $loginHint): UserAuthenticationResultInterface
            {
                // Authenticate user somehow
                return new UserAuthenticationResult(
                    true,
                    new UserIdentity(
                        $this->currentUser->getId(),
                        $this->currentUser->getFullName(),
                        $this->currentUser->getEmail()
                    )
                );
            }
        };

//        RegistrationRepositoryInterface::class
//
//        $registrationRepository = new
//        $authenticator = new OidcAuthenticator()
    }
}
