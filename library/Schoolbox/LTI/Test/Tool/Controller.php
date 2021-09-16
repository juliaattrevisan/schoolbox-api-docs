<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test\Tool;

use Alaress\Web\Controller\Base;
use Http\Factory\Guzzle\ServerRequestFactory;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcInitiator;
use Schoolbox\LTI\Test\RegistrationRepository;
use Schoolbox\LTI\Test\RegistrationRepositoryFactory;
use Schoolbox\LTI\Test\UserAuthenticator;
use Schoolbox\LTI\Test\UserAuthenticatorFactory;

/**
 * LTI test tool controller
 */
class Controller extends Base
{
    /**
     * Handle OIDC initiation request
     *
     * @api GET /lti-test/tool/oidc/initiation
     * @since 21.1.0
     */
    public function getOidcInitiationRequest()
    {
        // Handle the OIDC initiation on the tool side
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#2-tool-side-oidc-initiation
        $repository    = $this->buildRegistrationRepository();
        $oidcInitiator = new OidcInitiator($repository);


        // This library uses PSR-7 `\Psr\Http\Message\ServerRequestInterface`,
        // which Aura doesn't work with:
        // have to build one ourselves.
        $serverRequestFactory = new ServerRequestFactory();
        $serverRequest        = $serverRequestFactory->createServerRequest(
            '',
            $this->request->url->get(),
            $this->request->server->getArrayCopy(),
        );
        $message = $oidcInitiator->initiate($serverRequest);

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestTool/oidcInitiationRequest.twig',
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
        return (new RegistrationRepositoryFactory($this->getConfigLoader()))->build();
    }

    /**
     * Build the test user authenticator for this platform.
     *
     * @return UserAuthenticator
     */
    private function buildUserAuthenticator(): UserAuthenticator
    {
        return (new UserAuthenticatorFactory())->build($this->getUser());
    }
}
