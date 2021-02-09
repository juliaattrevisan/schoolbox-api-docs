<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\JwtVerifierFactory;

use Okta\JwtVerifier\JwtVerifier;

/**
 * Interface for JWT verifier factories.
 */
interface IFactory
{
    /**
     * Build a JWT verifier, if this factory is able to given its parameters.
     *
     * @param string $issuer   The JWT's issuer: used by the JWT verifier to
     *                         retrieve a set of keys used to decode the JWT.
     * @param string $clientId The JWT's client ID: dependent on the issuer,
     *                         this will have to match either or both the "cid"
     *                         and/or "aud" claims.
     * @param int    $leeway   The number of seconds that are allowable between
     *                         the current time and the expiry time of the JWT.
     * @return JwtVerifier|null
     */
    public function build(string $issuer, string $clientId, int $leeway): ?JwtVerifier;
}
