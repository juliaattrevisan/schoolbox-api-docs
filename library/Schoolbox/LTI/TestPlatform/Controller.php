<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\TestPlatform;

use Alaress\Web\Controller\Base;
use Http\Factory\Guzzle\ServerRequestFactory;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator;
use Schoolbox\Url\Factory;

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
        $registrationRepository = $this->buildRegistrationRepository();

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
            null,
            [
                $this->getUser()->getRole()->isStaff()
                    ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'
                    : 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'
            ],
            [
                new ResourceLinkClaim(
                    'test',
                    'http://devkit-lti1p3.localhost/platform/message/launch/lti-resource-link',
                    'Test resource link',
                ),
            ],
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/index.twig',
                ['messageUrl' => $message->toUrl(),]
            )
        );
    }

    /**
     * Authenticate an OIDC request
     *
     * @api GET /lti-test-platform/oidc/authentication
     * @since 21.1.0
     */
    public function getOidcAuthenticationRequest(): void
    {
        // Do something with the OIDC request when I receive it
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#3-platform-side-oidc-authentication
        $oidcAuthenticator = new OidcAuthenticator(
            $this->buildRegistrationRepository(),
            $this->buildUserAuthenticator()
        );

        // This library uses PSR-7 `\Psr\Http\Message\ServerRequestInterface`,
        // which Aura doesn't work with:
        // have to build one ourselves.
        $serverRequestFactory = new ServerRequestFactory();
        $serverRequest        = $serverRequestFactory->createServerRequest(
            '',
            $this->request->url->get(),
            $this->request->server->getArrayCopy(),
        );
        $message = $oidcAuthenticator->authenticate($serverRequest);

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/oidcAuthenticationRequest.twig',
                ['messageUrl' => $message->toUrl(),]
            )
        );
    }

    /**
     * Build the test registration repository for this platform.
     *
     * @return RegistrationRepository
     */
    private function buildRegistrationRepository(): RegistrationRepository
    {
        return new RegistrationRepository(
            (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https')->toString(),
        );
    }

    /**
     * Build the test user authenticator for this platform.
     *
     * @return UserAuthenticator
     */
    private function buildUserAuthenticator(): UserAuthenticator
    {
        return new UserAuthenticator($this->getUser()->getId() ? $this->getUser() : null);
    }
}
