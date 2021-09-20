<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test\Tool;

use Alaress\Web\Controller\Base;
use Cache\Adapter\PHPArray\ArrayCachePool;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Tool\ToolLaunchValidator;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepository;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcInitiator;
use Schoolbox\LTI\Test\RegistrationRepository;
use Schoolbox\LTI\Test\RegistrationRepositoryFactory;
use Schoolbox\LTI\Test\ServerRequestFactory;
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
        $serverRequest = (new ServerRequestFactory())->build($this->request);
        $message       = $oidcInitiator->initiate($serverRequest);

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestTool/oidcInitiationRequest.twig',
                ['message' => $message,]
            )
        );
    }

    /**
     * Handle launch request
     *
     * @api GET /lti-test/tool/launch
     * @since 21.1.0
     */
    public function getLaunch()
    {
        // Validate launch
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#4-tool-side-launch-validation
        $registrationRepository = $this->buildRegistrationRepository();
        $serverRequest          = (new ServerRequestFactory())->build($this->request);
        $validator              = new ToolLaunchValidator(
            $registrationRepository,
            new NonceRepository(new ArrayCachePool())
        );
        $result = $validator->validatePlatformOriginatingLaunch($serverRequest);
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestTool/launch.twig',
                ['result' => $result]
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
}
