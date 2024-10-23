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

        // Create the base message, but work around the LTI library's setting of
        // the message's URL as the LTI target link URI:
        // instead, create a message whose URL is the redirect URI from the
        // request.
        $messagePayloadBuilder = new MessagePayloadBuilder(
            isset($nonce) ? new NoncePassthrough((string)$nonce) : null,
            new JwtBuilder(new ConfigurationFactory()),
        );
        $baseMessage = (new OidcAuthenticator(
            $this->registrationRepository,
            $this->userAuthenticator,
            $messagePayloadBuilder,
        ))->authenticate($request);

        // Verify that redirect URI and client ID are in the request, and also
        // that the client ID matches a tool registration.
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

        // Validate the redirect URI against the tool's launch URL.
        // According to https://www.imsglobal.org/spec/security/v1p0/#step-3-authentication-response,
        // the redirect URI must be validated against one of the tool's redirect
        // URIs: however, the LTI library doesn't provide an official method
        // for exposing redirect URIs.
        // I'm just going to assume the launch and deep linking URLs are valid
        // redirects.
        $tool        = $registration->getTool();
        $validUrls   = array_filter([$tool->getLaunchUrl(), $tool->getDeepLinkingUrl()]);

        // Map this registration back to a configuration in Schoolbox,
        // and use its redirect URLs as well.
        /** @var Provider\Model $toolModel */
        $toolModel = $this->toolMapper->findOneByColumn('deployment_id', $registration->getIdentifier());
        foreach ($toolModel->getRedirectUrls() as $redirectUrl) {
            $validUrls[] = $redirectUrl->getRedirectUrl();
        }

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
        return new LtiMessage($redirectUri, $baseMessage->getParameters()->all());
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
