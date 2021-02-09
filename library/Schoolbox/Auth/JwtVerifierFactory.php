<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth;

use Okta\JwtVerifier\Adaptors\Adaptor;
use Okta\JwtVerifier\Discovery\Oidc;
use Okta\JwtVerifier\JwtVerifier;
use Okta\JwtVerifier\JwtVerifierBuilder;

/**
 * Factory for building different variants of JWT verifier for the different
 * oAuth providers which Schoolbox might consume.
 */
class JwtVerifierFactory
{
    /**
     * The issuer of the token.
     *
     * This will be used by the JWT verifier to retrieve a set of keys, which
     * will then be used to decode the JWT.
     */
    private string $issuer;

    /**
     * The client ID of the token.
     *
     * Dependent on the issuer of the JWT verifier: this will have to match
     * either or both the "cid" and/or "aud" claims in the JWT.
     */
    private string $clientId;

    /**
     * The amount of leeway that is allowable between the current time and the
     * expiry time of the JWT that is to be parsed.
     */
    private int $leeway;

    /**
     * Adaptor that the JWT verifier will use to decode the JWT.
     */
    private Adaptor $adaptor;

    /**
     * Constructor
     *
     * @param string  $issuer
     * @param string  $clientId
     * @param int     $leeway
     * @param Adaptor $adaptor
     */
    public function __construct(string $issuer, string $clientId, int $leeway, Adaptor $adaptor)
    {
        $this->issuer   = $issuer;
        $this->clientId = $clientId;
        $this->leeway   = $leeway;
        $this->adaptor  = $adaptor;
    }

    /**
     * @return JwtVerifier
     */
    public function build(): JwtVerifier
    {
        // Cloudwork variant
        return (new JwtVerifierBuilder())
            ->setIssuer($this->issuer)
            ->setAdaptor($this->adaptor)
            ->setDiscovery(new Oidc())
            ->setAudience([$this->clientId])
            ->setLeeway(sprintf('PT%dS', $this->leeway))
            ->build();

//        // Azure variant
//            return new JwtVerifier(
//                $this->issuer,
//                new Oidc(),
//                $this->adaptor,
//                null,
//                sprintf('PT%dS', $this->leeway),
//                ['audience' => $this->clientId,],
//        );
    }
}
