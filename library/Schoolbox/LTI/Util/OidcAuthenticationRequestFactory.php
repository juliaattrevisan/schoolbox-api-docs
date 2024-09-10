<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessage;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Builder\Builder as JwtBuilder;
use OAT\Library\Lti1p3Core\Security\Jwt\Parser\Parser;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\LTI\Platform\Context\ConfigurationFactory;
use Schoolbox\Url\Uri;

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
     * @param ServerRequestInterface $request
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface if the request couldn't be authenticated
     */
    public function build(ServerRequestInterface $request): LtiMessageInterface
    {
        // Nonce may be in either $_GET or $_POST
        // If it's not there: use the default nonce generator, I guess
        $nonce = match (strtolower($request->getMethod())) {
            'get'   => (new Uri($request->getUri()->__toString()))->getQueryAsArray()['nonce'] ?? null,
            'post'  => $request->getParsedBody()['nonce'] ?? null,
            default => null,
        };

        $configFactory         = new ConfigurationFactory();
        $messagePayloadBuilder = new MessagePayloadBuilder(
            isset($nonce) ? new NoncePassthrough((string)$nonce) : null,
            new JwtBuilder($configFactory)
        );

        $parser  = new Parser($configFactory);
        $message = (new OidcAuthenticator(
            $this->registrationRepository,
            $this->userAuthenticator,
            $messagePayloadBuilder,
            null,
            $parser,
        ))->authenticate($request);

        // Always return the launch URI
        $token = $parser->parse(LtiMessage::fromServerRequest($request)->getParameters()->get('lti_message_hint'));

        $registration = $this->registrationRepository->find(
            $token->getClaims()->getMandatory(MessagePayloadInterface::CLAIM_REGISTRATION_ID)
        );
        return (new LtiMessage($registration->getTool()->getLaunchUrl(), $message->getParameters()->all()));
    }
}
