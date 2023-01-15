<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Aura\Web\Request;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;

/**
 * Factory for OIDC authentication requests.
 */
class OidcAuthenticationRequestFactory
{
    /**
     * Repository of LTI registrations.
     */
    private RegistrationRepositoryInterface $registrationRepository;

    /**
     * User authenticator.
     */
    private UserAuthenticatorInterface $userAuthenticator;

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
        $this->registrationRepository = $registrationRepository;
        $this->userAuthenticator      = $userAuthenticator;
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
        // Nonce may be in either $_GET or $_POST
        if ($request->method->isGet()) {
            $nonce = $request->query->get('nonce', '');
        } elseif ($request->method->isPost()) {
            $nonce = $request->post->get('nonce', '');
        } else {
            // No nonce: pass nothing, I guess?
            $nonce = '';
        }

        return (new OidcAuthenticator(
            $this->registrationRepository,
            $this->userAuthenticator,
            new MessagePayloadBuilder(new NoncePassthrough((string)$nonce)),
        ))->authenticate((new ServerRequestFactory())->build($request));
    }
}
