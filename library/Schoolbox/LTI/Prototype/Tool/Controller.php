<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Tool;

use Alaress\Web\Controller\Base;
use Schoolbox\LTI\Prototype\RegistrationRepository;
use Schoolbox\LTI\Prototype\RegistrationRepositoryFactory;

/**
 * LTI prototype tool controller
 */
class Controller extends Base
{
    /**
     * Handle OIDC initiation request
     *
     * @api GET /lti-prototype/tool/oidc/initiation
     * @since 21.1.0
     */
    public function getOidcInitiationRequest()
    {
        // Handle the OIDC initiation on the tool side
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#2-tool-side-oidc-initiation
        $message = (new OidcInitiationRequestFactory($this->buildRegistrationRepository()))->build($this->request);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPrototypeTool/oidcInitiationRequest.twig',
                ['message' => $message,]
            )
        );
    }

    /**
     * Handle launch request
     *
     * @api GET /lti-prototype/tool/launch
     * @since 21.1.0
     */
    public function getLaunch()
    {
        // Validate launch
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/message/platform-originating-messages/#4-tool-side-launch-validation
        $result = (new PlatformOriginatingLaunchFactory($this->buildRegistrationRepository()))->build($this->request);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPrototypeTool/launch.twig',
                ['result' => $result]
            )
        );
    }

    /**
     * Build the registration repository for this platform.
     *
     * @return RegistrationRepository
     */
    private function buildRegistrationRepository(): RegistrationRepository
    {
        return (new RegistrationRepositoryFactory($this->getConfigLoader()))->build();
    }
}
