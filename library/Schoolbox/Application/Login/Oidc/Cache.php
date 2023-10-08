<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\Login\Oidc;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Helper for caching OIDC config and JWKS.
 */
class Cache
{
    /**
     * Default: cache config from OIDC discovery URI response for 24 hours.
     */
    private const OIDC_CONFIG_CACHE_DEFAULT_TTL = 86400;

    /**
     * Default: cache JWKS URI response 1 hour.
     */
    private const JWKS_CACHE_DEFAULT_TTL = 3600;

    /**
     * Where to cache OIDC config?
     */
    public const OIDC_CONFIG_CACHE_KEY = 'oidcConfig';

    /**
     * Where to cache JWKS?
     */
    public const JWKS_CACHE_KEY = 'jwks';

    /**
     * Constructor
     *
     * @param Client         $httpClient         for fetching OIDC discovery and JWKS URI responses
     * @param CacheInterface $baseCache          for caching responses from those URIs
     * @param int            $oidcConfigCacheTtl If provided: override time OIDC config from discovery URI is cached for
     * @param int            $jwksCacheTtl       If provided: override time JWKS is cached for
     */
    public function __construct(
        private Client $httpClient,
        private CacheInterface $baseCache,
        private int $oidcConfigCacheTtl = self::OIDC_CONFIG_CACHE_DEFAULT_TTL,
        private int $jwksCacheTtl = self::JWKS_CACHE_DEFAULT_TTL,
    ) {
    }

    /**
     * Retrieve OIDC config from the oAuth identity provider's OIDC discovery
     * URI.
     *
     * @param UriInterface $identityProviderUri
     * @return array
     * @throws ClientExceptionInterface if something went wrong fetching
     *                                  OIDC discovery URI
     * @throws InvalidArgumentException if OIDC discovery couldn't be written to
     *                                  or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function retrieveOidcConfig(UriInterface $identityProviderUri): array
    {
        $oidcConfig = $this->baseCache->get(self::OIDC_CONFIG_CACHE_KEY);
        if (!isset($oidcConfig)) {
            $oidcDiscoveryUri  = $identityProviderUri->withPath(
                $identityProviderUri->getPath() . '/.well-known/openid-configuration',
            );
            $oidcConfigRequest = $this->httpClient->get($oidcDiscoveryUri->__toString());
            $oidcConfig        = json_decode($oidcConfigRequest->getBody()->getContents(), true);
            $this->baseCache->set(self::OIDC_CONFIG_CACHE_KEY, $oidcConfig, $this->oidcConfigCacheTtl);
        }
        return $oidcConfig;
    }

    /**
     * Retrieve JSON web key set (JWKS) from the oAuth identity provider's OIDC
     * discovery URI's response.
     *
     * @param UriInterface $identityProviderUri
     * @return array
     * @throws ClientExceptionInterface if something went wrong fetching
     *                                  OIDC discovery or JWKS URIs
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function retrieveJsonWebKeySet(UriInterface $identityProviderUri): array
    {
        $oidcConfig = $this->retrieveOidcConfig($identityProviderUri);
        $jwks       = $this->baseCache->get(self::JWKS_CACHE_KEY);
        if (!isset($jwks)) {
            $jwksRequest       = $this->httpClient->get($oidcConfig['jwks_uri']);
            $jwks              = json_decode($jwksRequest->getBody()->getContents(), true);
            $this->baseCache->set(self::JWKS_CACHE_KEY, $jwks, $this->jwksCacheTtl);
        }
        return $jwks;
    }
}
