<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Auth;

use OAT\Library\Lti1p3Core\Exception\LtiBadRequestException;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Builder\MessagePayloadBuilderInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Parser\ParserInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\Validator\ValidatorInterface;
use OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator as BaseOidcAuthenticator;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use Schoolbox\LTI\Provider;
use Schoolbox\Mapper\Exception;

/**
 * Extension of `\OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator`,
 * which allows for the redirect URI to have a different host component to that
 * of the tool's launch URL.
 */
class OidcAuthenticator extends BaseOidcAuthenticator
{
    /**
     * Constructor
     *
     * @param RegistrationRepositoryInterface     $repository    Repository of LTI registrations.
     * @param UserAuthenticatorInterface          $authenticator Authenticates the user, based on the login hint in the
     *                                                           request.
     * @param MessagePayloadBuilderInterface|null $builder       LTI message payload builder: if not provided, use a
     *                                                           default implementation.
     * @param ValidatorInterface|null             $validator     JWT validator: if not provided, use a default
     *                                                           implementation.
     * @param ParserInterface|null                $parser        JWT parser: if not provided, use a default
     *                                                           implementation.
     * @param Provider\Mapper $toolMapper                        Mapper for LTI tool registrations: required for
     *                                                           fetching the tool's redirect URLs.
     */
    public function __construct(
        RegistrationRepositoryInterface $repository,
        UserAuthenticatorInterface $authenticator,
        ?MessagePayloadBuilderInterface $builder,
        ?ValidatorInterface $validator,
        ?ParserInterface $parser,
        private Provider\Mapper $toolMapper,
    ) {
        parent::__construct($repository, $authenticator, $builder, $validator, $parser);
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
    protected function createToolRedirectUrl(
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
