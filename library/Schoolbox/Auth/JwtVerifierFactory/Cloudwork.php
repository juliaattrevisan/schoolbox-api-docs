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
use Okta\JwtVerifier\JwtVerifierBuilder;

/**
 * Factory for the Cloudwork variant of the JWT verifier.
 *
 * For now this is the default variant, so it will always create a verifier:
 * in practice the issuer will be "https://{$tenantId}.cloudworkengine.net/".
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
        return (new JwtVerifierBuilder())
            ->setIssuer($issuer)
            ->setAdaptor($this->adaptor)
            ->setDiscovery(new Oidc())
            ->setAudience([$clientId])
            ->setLeeway(sprintf('PT%dS', $leeway))
            ->build();
    }
}
