<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Builder\Builder as JwtBuilder;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\LTI\Auth\OidcAuthenticator;
use Schoolbox\LTI\Platform\Context\ConfigurationFactory;
use Schoolbox\LTI\Provider;
use Schoolbox\Url\Uri;

/**
 * Factory for OIDC authentication requests.
 */
class OidcAuthenticationRequestFactory
{
    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface $registrationRepository Repository of LTI registrations.
     * @param UserAuthenticatorInterface      $userAuthenticator      User authenticator.
     * @param Provider\Mapper                 $toolMapper             Tool mapper, for fetching configuration when
     *                                                                building authentication requests.
     */
    public function __construct(
        private RegistrationRepositoryInterface $registrationRepository,
        private UserAuthenticatorInterface $userAuthenticator,
        private Provider\Mapper $toolMapper,
    ) {
    }

    /**
     * Build the OIDC authentication request.
     *
     * @param ServerRequestInterface $request
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface if the request couldn't be authenticated
     */
    public function build(ServerRequestInterface $request): LtiMessageInterface
    {
        // Nonce may be in either $_GET or $_POST
        // If it's not there: use the default nonce generator, I guess
        $requestParams = match (strtolower($request->getMethod())) {
            'get'   => (new Uri($request->getUri()->__toString()))->getQueryAsArray(),
            'post'  => $request->getParsedBody(),
            default => [],
        };
        $nonce = $requestParams['nonce'] ?? null;

        $messagePayloadBuilder = new MessagePayloadBuilder(
            isset($nonce) ? new NoncePassthrough((string)$nonce) : null,
            new JwtBuilder(new ConfigurationFactory()),
        );
        return (new OidcAuthenticator(
            $this->registrationRepository,
            $this->userAuthenticator,
            $messagePayloadBuilder,
            null,
            null,
            $this->toolMapper,
        ))->authenticate($request);
    }
}
