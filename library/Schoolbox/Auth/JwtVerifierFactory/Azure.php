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
 * Factory for the Azure variant of the JWT verifier.
 *
 * In order to use this variant, the issuer must match
 * "https://login.microsoftonline.com/{$tenantId}/v2.0/".
 *
 * Peculiarities:
 * * The issuer will be "https://login.microsoftonline.com/{$tenantId}/v2.0/"
 * * The JWT uses OpenID Connect for key discovery
 * * The JWT is not guaranteed to contain a "cid" claim, so don't try to
 *   validate that
 */
class Azure implements IFactory
{
    private const AZURE_HOST = 'login.microsoftonline.com';

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
        $host = parse_url($issuer, PHP_URL_HOST);
        if ($host !== self::AZURE_HOST) {
            return null;
        }
        return new JwtVerifier(
            $issuer,
            new Oidc(),
            $this->adaptor,
            null,
            $leeway,
            ['audience' => $clientId],
        );
    }
}
