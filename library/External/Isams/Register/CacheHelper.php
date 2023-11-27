<?php

namespace External\Isams\Register;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Uri;

class CacheHelper
{
    /**
     * Default: cache JWKS URI response 1 hour.
     */
    private const JWKS_CACHE_DEFAULT_TTL = 3600;

    /**
     * Where to cache JWKS?
     */
    public const JWKS_CACHE_KEY = 'isams_reg_jwks';

    /**
     * Constructor
     *
     * @param HttpClient $httpClient for querying iSams
     * @param CacheInterface $cache for caching the api response
     */
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache, private IConfigLoader $configLoader)
    {
    }


    /**
     * Retrieve Bearer Token from the OIDC
     *
     * @return string
     * @throws ClientExceptionInterface if something went wrong fetching
     *                                  OIDC discovery or JWKS URIs
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getToken(): string
    {
        $jwks       = $this->cache->get(self::JWKS_CACHE_KEY);
        // does the cache exist? then lets use it. else generate and request the jwks
        if (!isset($jwks)) {
            // first, need to exchange client id/secret for an access token
            $apiUrl        = new Uri($this->configLoader->get('external_isams_rest_api_host'));
            $tokenResponse = $this->httpClient->post(
                $apiUrl->withPath('/auth/connect/token'),
                [
                    RequestOptions::FORM_PARAMS => [
                        'client_id'     => $this->configLoader->get('external_isams_rest_api_client_id'),
                        'client_secret' => $this->configLoader->get('external_isams_rest_api_client_secret'),
                        'grant_type'    => 'client_credentials',
                        'scope'         => 'restapi',
                    ]
                ]
            );
            $jwks = json_decode($tokenResponse->getBody()->getContents(), true);

            $this->cache->set(self::JWKS_CACHE_KEY, $jwks, $jwks['expires_in'] ?? self::JWKS_CACHE_DEFAULT_TTL);
        }
        return $jwks['access_token'];
    }
}
