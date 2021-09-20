<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Platform;

use Aura\Web\Request;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use Schoolbox\LTI\Prototype\ServerRequestFactory;

/**
 * Factory for OIDC authentication requests.
 */
class OidcAuthenticationRequestFactory
{
    /**
     * OIDC authenticator, created in constructor.
     */
    private OidcAuthenticator $oidcAuthenticator;

    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrationRepository
     * @param UserAuthenticatorInterface      $userAuthenticator
     */
    public function __construct(
        RegistrationRepositoryInterface $registrationRepository,
        UserAuthenticatorInterface $userAuthenticator
    ) {
        $this->oidcAuthenticator = new OidcAuthenticator($registrationRepository, $userAuthenticator);
    }

    /**
     * Build the OIDC authentication request.
     *
     * @param Request $request
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface if the request couldn't be authenticated
     */
    public function build(Request $request): LtiMessageInterface
    {
        return $this->oidcAuthenticator->authenticate((new ServerRequestFactory())->build($request));
    }
}
