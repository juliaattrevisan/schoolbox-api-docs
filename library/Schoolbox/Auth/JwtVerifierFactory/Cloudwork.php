<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\JwtVerifierFactory;

use Okta\JwtVerifier\Adaptors\Adaptor;
use Okta\JwtVerifier\Discovery\Oidc;
use Okta\JwtVerifier\JwtVerifier;

/**
 * Factory for the Cloudwork variant of the JWT verifier.
 *
 * For now this is the default variant, so it will always create a verifier:
 * in practice the issuer will be "https://{$tenantId}.cloudworkengine.net/".
 *
 * Peculiarities:
 * * The JWT uses OpenID Connect for key discovery
 * * The JWT is not guaranteed to contain a "cid" claim, so don't try to
 *   validate that
 * * The JWT's "aud" (audience) claim is an array containing only the client ID
 *   for some reason, instead of just the client ID. Maybe the JWT is able to be
 *   targeted to multiple audiences?
 */
class Cloudwork implements IFactory
{
    /**
     * Adaptor that the JWT verifier will use to decode the JWT.
     */
    private Adaptor $adaptor;

    /**
     * Constructor
     *
     * @param Adaptor $adaptor
     */
    public function __construct(Adaptor $adaptor)
    {
        $this->adaptor = $adaptor;
    }

    /**
     * @inheritdoc
     */
    public function build(string $issuer, string $clientId, int $leeway): ?JwtVerifier
    {
        return new JwtVerifier(
            $issuer,
            new Oidc(),
            $this->adaptor,
            null,
            $leeway,
            ['audience' => [$clientId],],
        );
    }
}
