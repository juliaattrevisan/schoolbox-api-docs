<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use DateTimeImmutable;
use OAT\Library\Lti1p3Core\Security\Nonce\Nonce;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGeneratorInterface;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceInterface;

/**
 * Nonce "generator" for use within responses to OIDC authentication requests.
 *
 * https://www.imsglobal.org/spec/security/v1p0/#step-2-authentication-request
 * states that the nonce value is supposed to be passed from the authentication
 * request into the ID token provided in the response.
 *
 * https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation also
 * states that the nonce should be passed unchanged into the ID token.
 *
 * `\OAT\Library\Lti1p3Core\Security\Oidc\OidcAuthenticator::authenticate`
 * doesn't do this with its default `$builder` instance: this instance instead
 * creates a new nonce. I can't see how this isn't a bug with that library, but
 * it seems like a massive oversight?
 *
 * Anyway. This just generates a nonce with the value `$nonce`, and an optional
 * TTL value.
 */
class PassthroughNonceGenerator implements NonceGeneratorInterface
{
    /**
     * Constructor
     *
     * @param string $nonce The value to add to the nonce built by `$this->generate`.
     * @param int    $ttl   The TTL for the nonce: default to the interface's default.
     */
    public function __construct(private string $nonce, private int $ttl = self::TTL)
    {
    }

    /**
     * Generate the nonce.
     *
     * @param int|null $ttl If provided, use this TTL instead of `$this->ttl`.
     * @return NonceInterface
     */
    public function generate(?int $ttl = null): NonceInterface
    {
        return new Nonce(
            $this->nonce,
            (new DateTimeImmutable())->modify(sprintf('%d sec', ($ttl ?? $this->ttl))),
        );
    }
}
