<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Auth;

use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Exception\LtiBadRequestException;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessage;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilder;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilderInterface;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Parser\Parser;
use OAT\Library\Lti1p3Core\Security\Jwt\Parser\ParserInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Validator\Validator;
use OAT\Library\Lti1p3Core\Security\Jwt\Validator\ValidatorInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\LTI\Provider;
use Schoolbox\Mapper\Exception;
use Throwable;

/**
 * Re-implementation of `\OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator`,
 * which allows for the redirect URI to have a different host component to that
 * of the tool's launch URL.
 *
 * @see \OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator
 */
class OidcAuthenticator
{
    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface     $repository    Repository of LTI registrations.
     * @param UserAuthenticatorInterface          $authenticator Authenticates the user, based on the login hint in the
     *                                                           request.
     * @param Provider\Mapper                     $toolMapper    Mapper for LTI tool registrations: required for
     *                                                           fetching the tool's redirect URLs.
     * @param MessagePayloadBuilderInterface|null $builder       LTI message payload builder: if not provided, use a
     *                                                           default implementation.
     * @param ValidatorInterface|null             $validator     JWT validator: if not provided, use a default
     *                                                           implementation.
     * @param ParserInterface|null                $parser        JWT parser: if not provided, use a default
     *                                                           implementation.
     */
    public function __construct(
        private RegistrationRepositoryInterface $repository,
        private UserAuthenticatorInterface $authenticator,
        private Provider\Mapper $toolMapper,
        private ?MessagePayloadBuilderInterface $builder = null,
        private ?ValidatorInterface $validator = null,
        private ?ParserInterface $parser = null
    ) {
        $this->builder   ??= new MessagePayloadBuilder();
        $this->validator ??= new Validator();
        $this->parser    ??= new Parser();
    }

    /**
     * Authenticate the OIDC request, and return a response message.
     *
     * @param ServerRequestInterface $request Incoming HTTP request.
     * @return LtiMessageInterface The message to send back to the tool.
     * @throws LtiExceptionInterface if the request is invalid for some reason
     */
    public function authenticate(ServerRequestInterface $request): LtiMessageInterface
    {
        try {
            $oidcRequest = LtiMessage::fromServerRequest($request);

            if (!$oidcRequest->getParameters()->has('lti_message_hint')) {
                throw new LtiBadRequestException('Missing LTI message hint in request');
            }

            $originalToken = $this->parser->parse($oidcRequest->getParameters()->getMandatory('lti_message_hint'));

            $registration = $this->repository->find(
                $originalToken->getClaims()->getMandatory(MessagePayloadInterface::CLAIM_REGISTRATION_ID)
            );

            if (null === $registration) {
                throw new LtiBadRequestException('Invalid message hint registration id claim');
            }

            if (!$this->validator->validate($originalToken, $registration->getPlatformKeyChain()->getPublicKey())) {
                throw new LtiBadRequestException('Invalid message hint');
            }

            $redirectUri = $this->createToolRedirectUrl($registration, $oidcRequest);

            $authenticationResult = $this->authenticator->authenticate(
                $registration,
                $oidcRequest->getParameters()->getMandatory('login_hint')
            );

            if (!$authenticationResult->isSuccess()) {
                throw new LtiException('User authentication failure');
            }

            $this->builder
                ->withClaims($this->sanitizeClaims($originalToken->getClaims()->all()))
                ->withClaim(MessagePayloadInterface::CLAIM_ISS, $registration->getPlatform()->getAudience())
                ->withClaim(MessagePayloadInterface::CLAIM_AUD, $registration->getClientId());

            // If the original request contained a nonce, add it to the payload (fixes #154)
            $nonce = $oidcRequest->getParameters()->get('nonce');
            if ($nonce) {
                $this->builder->withClaim(MessagePayloadInterface::CLAIM_NONCE, $nonce);
            }

            if (!$authenticationResult->isAnonymous()) {
                foreach ($authenticationResult->getUserIdentity()->normalize() as $claimName => $claimValue) {
                    $this->builder->withClaim($claimName, $claimValue);
                }
            }

            $payload = $this->builder->buildMessagePayload($registration->getPlatformKeyChain());

            return new LtiMessage(
                $redirectUri,
                [
                    'id_token' => $payload->getToken()->toString(),
                    'state'    => $oidcRequest->getParameters()->getMandatory('state')
                ]
            );
        } catch (LtiExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LtiException(
                sprintf('OIDC authentication failed: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Unset all reserved user claims from the payload of the LTI message to
     * return on authentication.
     *
     * @param array $claims List of claims from the incoming request.
     * @return array
     */
    private function sanitizeClaims(array $claims): array
    {
        foreach (MessagePayloadInterface::RESERVED_USER_CLAIMS as $reservedClaim) {
            unset($claims[$reservedClaim]);
        }

        return $claims;
    }

    /**
     * Extract and validate the tool redirect URL from the OIDC request.
     *
     * The LTI library doesn't natively support redirect URLs within its
     * objects, so the redirect URLs must be fetched from the database
     * representation of the LTI tool.
     *
     * @param RegistrationInterface $registration The LTI tool registration.
     * @param LtiMessageInterface   $oidcRequest  The request to validate.
     * @return string                 The validated redirect URL.
     * @throws LtiBadRequestException if the request's redirect URL did not
     *                                match either:
     *                                * the tool's launch URL
     *                                * the tool's deep linking URL
     *                                * one of the tool's redirect URLs
     */
    private function createToolRedirectUrl(
        RegistrationInterface $registration,
        LtiMessageInterface $oidcRequest
    ): string {
        // Fetch the LTI tool model for this registration
        try {
            $tool = $this->toolMapper->findOneByClientId($registration->getClientId());
        } catch (Exception $e) {
            throw new LtiBadRequestException('Registration ID does not match a configured LTI tool', 0, $e);
        }

        // Validate the redirect URI against the tool's launch URL.
        // According to https://www.imsglobal.org/spec/security/v1p0/#step-3-authentication-response,
        // the redirect URI must be validated against one of the tool's redirect
        // URIs: however, the LTI library doesn't provide an official method for
        // exposing redirect URIs.
        // I'm just going to assume the launch and deep linking URLs are valid
        // redirects.
        $validUrls = array_filter([$tool->getLaunchUrl(), $tool->getDeepLinkingUrl()]);
        foreach ($tool->getRedirectUrls() as $redirectUrlModel) {
            /** @var Provider\RedirectUrl\Model $redirectUrlModel */
            $validUrls[] = $redirectUrlModel->getRedirectUrl();
        }

        // Extract and validate the request's redirect_uri
        $redirectUri = $this->normalizeUri($oidcRequest->getParameters()->getMandatory('redirect_uri'));
        foreach ($validUrls as $validUrl) {
            $validUrl = $this->normalizeUri($validUrl);
            if ($validUrl === $redirectUri) {
                return $redirectUri;
            }
        }

        throw new LtiBadRequestException(
            'Invalid redirect_uri: does not match the tool\'s launch or deep linking URL, nor any of its redirect URLs'
        );
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
