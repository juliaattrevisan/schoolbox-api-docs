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
use Schoolbox\User;

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
     * Default: cache student data 30 min.
     */
    private const STUDENT_CACHE_DEFAULT_TTL = 1800;

    /**
     * Where to cache JWKS?
     */
    public const JWKS_CACHE_KEY = 'isams_registers_jwks';

    /**
     * Where to cache the registers array
     */
    public const REGISTERS_CACHE_KEY = 'isams_registers';

    /**
     * Where to cache the metadata array
     */
    public const METADATA_CACHE_KEY = 'isams_register_%s_metadata';

    /**
     * Where to cache the student dates array
     */
    public const STUDENT_DATES_CACHE_KEY = 'isams_register_%s_student_dates_%s';

    /**
     * Where to cache the student fields array
     */
    public const STUDENT_FIELDS_CACHE_KEY = 'isams_register_%s_student_fields_%s';

    /**
     * Where to cache the student fields array
     */
    public const DATASOURCE_FIELDS_CACHE_KEY = 'isams_register_datasource_%s';

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

    /**
     * Retrieve the student date ranges for the specified Register ID
     *
     * @param int    $registerId
     * @param string $externalId
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    public function getStudentDateRanges(int $registerId, string $externalId)
    {
        $dateRanges = $this->cache->get(sprintf(self::STUDENT_DATES_CACHE_KEY, $registerId, $externalId));

        if (!isset($dateRanges)) {
            // Request 1.2: find date ranges for student
            try {
                $dateRangesResponse     = $this->httpClient->get(
                    $this->apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $externalId . '/dateranges'),
                    [
                        RequestOptions::HEADERS => [
                            'authorization' => 'Bearer ' . $this->getToken(),
                        ],
                    ],
                );
                $dateRanges = json_decode($dateRangesResponse->getBody()->getContents(), true);
                $this->cache->set(sprintf(self::STUDENT_DATES_CACHE_KEY, $registerId, $externalId), $dateRanges, self::STUDENT_CACHE_DEFAULT_TTL);
            } catch (ClientExceptionInterface $e) {
                // No record exists for this student: that's okay
                return [];
            }
        }
        return $dateRanges['dateRanges'];
    }

    /**
     * Retrieve the student date ranges for the specified Register ID
     *
     * @param int    $registerId
     * @param string $externalId
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    public function getStudentFields(int $registerId, string $externalId)
    {
        $studentFields = $this->cache->get(sprintf(self::STUDENT_FIELDS_CACHE_KEY, $registerId, $externalId));

        if (!isset($studentFields)) {
            // Request 1.3: find fields for student
            try {
                $fieldsResponse     = $this->httpClient->get(
                    $this->apiUrl->withPath('/Main/api/registers/' . $registerId . '/students/' . $externalId . '/fields'),
                    [
                        RequestOptions::HEADERS => [
                            'authorization' => 'Bearer ' . $this->getToken(),
                        ],
                    ],
                );
                $studentFields = json_decode($fieldsResponse->getBody()->getContents(), true);
                $this->cache->set(sprintf(self::STUDENT_FIELDS_CACHE_KEY, $registerId, $externalId), $studentFields, self::STUDENT_CACHE_DEFAULT_TTL);
            } catch (ClientExceptionInterface $e) {
                // No record exists for this student: that's okay
                return [];
            }
        }
        return $studentFields['fields'];
    }

    /**
     * Retrieve the Data Source as specified by the Metadata Field
     * @see self::getMetadataFields
     *
     * @param string $dataSourceUrl
     * @return array|mixed
     * @throws InvalidArgumentException
     */
    public function getDataSource(string $dataSourceUrl)
    {
        $dataSource = $this->cache->get(sprintf(self::DATASOURCE_FIELDS_CACHE_KEY, $dataSourceUrl));

        if (!isset($dataSource)) {
            try {
                $dataSourceResponse     = $this->httpClient->get(
                    $dataSourceUrl,
                    [
                        RequestOptions::HEADERS => [
                            'authorization' => 'Bearer ' . $this->getToken(),
                        ],
                    ],
                );
                $dataSource = json_decode($dataSourceResponse->getBody()->getContents(), true);
                $this->cache->set(sprintf(self::DATASOURCE_FIELDS_CACHE_KEY, $dataSourceUrl), $dataSource, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ClientExceptionInterface $e) {
                // No metadata exists for this field: that's okay
                return [];
            }
        }
        return $dataSource['items'];
    }
}
