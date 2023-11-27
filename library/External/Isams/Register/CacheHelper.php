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
     * Default: cache storage response 30 min.
     */
    private const STORAGE_CACHE_DEFAULT_TTL = 1800;

    /**
     * Where to cache JWKS?
     */
    public const JWKS_CACHE_KEY = 'isams_reg_jwks';

    /**
     * Where to cache the registers array
     */
    public const REGISTERS_CACHE_KEY = 'isams_reg_registers';

    /**
     * Where to cache the metadata array
     */
    public const METADATA_CACHE_KEY = 'isams_reg_metadata_%s';

    /**
     * Constructor
     *
     * @param HttpClient $httpClient for querying iSams
     * @param CacheInterface $cache for caching the api response
     */
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache, private IConfigLoader $configLoader, private Uri $apiUrl)
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
            $tokenResponse = $this->httpClient->post(
                $this->apiUrl->withPath('/auth/connect/token'),
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

    /**
     * Retrieve the Registers from Isams
     *
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRegisters()
    {
        $registers = $this->cache->get(self::REGISTERS_CACHE_KEY);
        if (!isset($registers)) {
            // Request 1: find registers
            // TODO these should be configured:
            //      also a mapping from codes to IDs should be cached
            $apiResponse     = $this->httpClient->get(
                $this->apiUrl->withPath('/Main/api/registers/'),
                [
                    RequestOptions::HEADERS => [
                        'authorization' => 'Bearer ' . $this->getToken(),
                    ],
                ],
            );
            $apiResponseBody = json_decode($apiResponse->getBody()->getContents(), true);
            $registers       = $apiResponseBody['registers'];
            $this->cache->set(self::REGISTERS_CACHE_KEY, $registers, self::STORAGE_CACHE_DEFAULT_TTL);
        }
        return $registers;
    }

    /**
     * Retrieve the metadata fields for the specified Register ID
     *
     * @param int $registerId
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    public function getMetadataFields(int $registerId)
    {
        $metadata = $this->cache->get(sprintf(self::METADATA_CACHE_KEY, $registerId));

        if (!isset($metadata)) {
            // This ought to be cacheable for a lot longer than student data
            try {
                $metadataResponse     = $this->httpClient->get(
                    $this->apiUrl->withPath('/Main/api/registers/' . $registerId . '/metadata'),
                    [
                        RequestOptions::HEADERS => [
                            'authorization' => 'Bearer ' . $this->getToken(),
                        ],
                    ],
                );
                $metadata = json_decode($metadataResponse->getBody()->getContents(), true);
                $this->cache->set(sprintf(self::METADATA_CACHE_KEY, $registerId), $metadata, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ClientExceptionInterface $e) {
                // No metadata exists for this field: that's okay
                return  [];
            }
        }
        return $metadata['fields'];
    }
}
