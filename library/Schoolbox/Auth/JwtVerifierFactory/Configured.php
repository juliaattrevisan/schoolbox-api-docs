<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\JwtVerifierFactory;

use Okta\JwtVerifier\Adaptors\Adaptor;
use Okta\JwtVerifier\JwtVerifier;

/**
 * Factory for JWT verifiers, based upon the configured identity provider type.
 */
class Configured implements IFactory
{
    private const IDENTITY_PROVIDER_TYPE_GENERIC = 'generic';
    private const IDENTITY_PROVIDER_TYPE_URL = 'url';
    private const IDENTITY_PROVIDER_TYPE_CLOUDWORK = 'cloudwork';
    private const IDENTITY_PROVIDER_TYPE_AZURE = 'azure';
    private const IDENTITY_PROVIDER_TYPE_ADFS = 'adfs';

    /**
     * What type of identity provider is being configured?
     *
     * Will be one of the `self::IDENTITY_PROVIDER_TYPE_*` constants.
     */
    private string $identityProviderType;

    /**
     * Adaptor required to create other `IFactory` instances, which will then
     * be used to create the JWT verifier.
     */
    private Adaptor $adaptor;

    /**
     * Constructor
     *
     * @param string  $identityProviderType
     * @param Adaptor $adaptor
     */
    public function __construct(string $identityProviderType, Adaptor $adaptor)
    {
        $this->identityProviderType = $identityProviderType;
        $this->adaptor              = $adaptor;
    }

    /**
     * @inheritdoc
     */
    public function build(string $issuer, string $clientId, int $leeway): ?JwtVerifier
    {
        switch ($this->identityProviderType) {
            // Azure: always use the Azure verifier factory
            // (will likely work with the generic factory too)
            case self::IDENTITY_PROVIDER_TYPE_AZURE:
                $factory = new Azure($this->adaptor);
                break;

            // Azure: always use the Cloudwork verifier factory
            case self::IDENTITY_PROVIDER_TYPE_CLOUDWORK:
                $factory = new Cloudwork($this->adaptor);
                break;

            // ADFS or generic: create a generic verifier factory
            case self::IDENTITY_PROVIDER_TYPE_ADFS:
            case self::IDENTITY_PROVIDER_TYPE_GENERIC:
                $factory = new Generic($this->adaptor);
                break;

            // URL-based: create the same factory as before
            // (Azure, then Cloudwork)
            case self::IDENTITY_PROVIDER_TYPE_URL:
            default:
                $factory = new Composite(
                    new Azure($this->adaptor),
                    new Cloudwork($this->adaptor),
                );
                break;
        }
        return $factory->build($issuer, $clientId, $leeway);
    }
}
