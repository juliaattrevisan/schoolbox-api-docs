<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Tool;

use Aura\Web\Request;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcInitiator;
use Schoolbox\LTI\Prototype\ServerRequestFactory;

/**
 * Factory for OIDC initiation requests.
 */
class OidcInitiationRequestFactory
{
    /**
     * OIDC initiator: created in the constructor.
     */
    private OidcInitiator $oidcInitiator;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrationRepository
     */
    public function __construct(RegistrationRepositoryInterface $registrationRepository)
    {
        $this->oidcInitiator = new OidcInitiator($registrationRepository);
    }

    /**
     * Build the OIDC initiation request.
     *
     * @param Request $request
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface
     */
    public function build(Request $request): LtiMessageInterface
    {
        return $this->oidcInitiator->initiate((new ServerRequestFactory())->build($request));
    }
}
