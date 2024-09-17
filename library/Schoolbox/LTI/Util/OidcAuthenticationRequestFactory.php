<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessage;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Builder\Builder as JwtBuilder;
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

        $message = (new OidcAuthenticator(
            $this->registrationRepository,
            $this->userAuthenticator,
            $messagePayloadBuilder,
        ))->authenticate($request);

        // Validate the redirect URI against the tool's launch URL.
        // According to https://www.imsglobal.org/spec/security/v1p0/#step-3-authentication-response,
        // the redirect URI must be validated against one of the tool's redirect
        // URIs: however, the LTI library doesn't provide an official method
        // for exposing redirect URIs.
        // I'm just going to assume the launch and deep linking URLs are valid
        // redirects.
        $redirectUri = $requestParams['redirect_uri'] ?? null;
        if (!isset($redirectUri)) {
            throw new LtiException('Mandatory request parameter "redirect_uri" missing');
        }
        $clientId = $requestParams['client_id'] ?? null;
        if (!isset($clientId)) {
            throw new LtiException('Mandatory request parameter "client_id" missing');
        }
        $registration = $this->registrationRepository->findByClientId($clientId);
        if (!$registration) {
            throw new LtiException('Request parameter "client_id" value does not match the client ID of an LTI tool');
        }

        // Validate redirect URI
        $tool      = $registration->getTool();
        $validUrls = array_filter([$tool->getLaunchUrl(), $tool->getDeepLinkingUrl()]);
        $redirectUri = $this->normalizeUri($redirectUri);
        $isRedirectUriValid = false;
        foreach ($validUrls as $validUrl) {
            $validUrl = $this->normalizeUri($validUrl);
            if ($validUrl === $redirectUri) {
                $isRedirectUriValid = true;
                break;
            }
        }
        if (!$isRedirectUriValid) {
            throw new LtiException(
                'Request parameter "redirect_uri" value does not match the LTI tool\'s launch or deep linking URL'
            );
        }

        // If the redirect URI is valid: create a message which sends to it.
        return (new LtiMessage($redirectUri, $message->getParameters()->all()));
    }

    /**
     * Normalize a URI string, trimming space and / characters.
     *
     * @param string $uri
     * @return string
     */
    private function normalizeUri(string $uri): string
    {
        return rtrim(trim($uri), '/');
    }
}
