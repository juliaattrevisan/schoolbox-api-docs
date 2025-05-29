<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Config\ConfigMapper;
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
     * Where to cache the record collections for the specific register
     */
    public const RECORD_COLLECTIONS_CACHE_KEY = 'isams_register_%s_recordCollections';

    /**
     * Where to cache the student records for the specified collection and student
     */
    public const RECORD_COLLECTION_STUDENT_CACHE_KEY = 'isams_register_%s_collection_%s_student_%s';

    private API $api;

    /**
     * Constructor
     *
     * @param HttpClient     $httpClient    for querying iSams
     * @param CacheInterface $cache         for caching the api response
     * @param IConfigLoader  $configLoader  to extract other secrects
     * @param Uri            $apiUrl        base api endpoint to work with
     */
    public function __construct(private HttpClient $httpClient, private CacheInterface $cache, private IConfigLoader $configLoader, private Uri $apiUrl)
    {
        $this->api = new API($this->httpClient, $this->apiUrl);
    }


    /**
     * Retrieve Bearer Token from the OIDC
     *
     * @return string
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getToken(): string
    {
        $jwks       = $this->cache->get(self::JWKS_CACHE_KEY);
        // does the cache exist? then lets use it. else generate and request the jwks
        if (!is_array($jwks)) {
            // first, need to exchange client id/secret for an access token
            $jwks = $this->api->request(
                '/auth/connect/token',
                'POST',
                [
                    RequestOptions::FORM_PARAMS => [
                        'client_id'     => $this->configLoader->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_CLIENT_ID),
                        'client_secret' => $this->configLoader->get(ConfigMapper::EXTERNAL_ISAMS_REST_API_CLIENT_SECRET),
                        'grant_type'    => 'client_credentials',
                        'scope'         => 'restapi',
                    ],
                ]
            );

            $this->cache->set(self::JWKS_CACHE_KEY, $jwks, $jwks['expires_in'] ?? self::JWKS_CACHE_DEFAULT_TTL);
        }
        return $jwks['access_token'];
    }

    /**
     * Retrieve the Registers from Isams
     *
     * @param string|null $registerCode if exists only return this register
     * @param array|null  $registersConfig if we are returning only 1 register, then confirm it is in the admin approved list
     * @return ?array [register,adminConfig]
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getRegisters(?string $registerCode = null, ?array $registersConfig = null): ?array
    {
        $registers = $this->cache->get(self::REGISTERS_CACHE_KEY);
        if (!isset($registers)) {
            try {
                // Request 1: find registers
                $apiResponseBody = $this->api->request(
                    '/Main/api/registers/',
                    'GET',
                    token: $this->getToken(),
                );
                $registers = $apiResponseBody['registers'];
                $this->cache->set(self::REGISTERS_CACHE_KEY, $registers, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
                throw new Exception(sprintf(
                    'Failed to retrieve registers from iSAMS: %s',
                    $e->getMessage()
                ), $e->getCode(), $e);
            }
        }
        // not fetching a specific register, get everything
        if (null === $registerCode && null === $registersConfig) {
            return $registers;
        }

        // we have some config that we want to limit to, do that
        foreach ($registers as $register) {
            /**
             * if the requested register is not in the allowed registers
             * OR
             * if the requested register is not in the current register
             * then continue onto the next register
             *
             * we will either only display 1 register, or return null.
             */
            if (!isset($registersConfig[strtolower($register['code'])]) || strtolower($register['code']) !== strtolower($registerCode)) {
                // the requested register Ignoring...
                continue;
            }
            return [$register, $registersConfig[strtolower($register['code'])]];
        }
        return null;
    }

    /**
     * For a specified register ID, retrieve the RegisterCollection from Isams
     *
     * @param int         $registerId
     * @param string|null $reportingKey RecordCollection.reportingKey (optional)
     * @return array|null
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getRecordCollections(int $registerId, ?string $reportingKey = null): ?array
    {
        $recordCollections = $this->cache->get(sprintf(self::RECORD_COLLECTIONS_CACHE_KEY, $registerId));

        if (!isset($recordCollections)) {
            try {
                // This ought to be cacheable for a lot longer than student data
                $apiResponseBody = $this->api->request(
                    '/Main/api/registers/' . $registerId . '/recordCollections',
                    'GET',
                    token: $this->getToken(),
                );
                $recordCollections = $apiResponseBody['recordCollections'];
                $this->cache->set(sprintf(self::RECORD_COLLECTIONS_CACHE_KEY, $registerId), $recordCollections, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
                throw new Exception(sprintf(
                    'Failed to retrieve registers from iSAMS: %s',
                    $e->getMessage()
                ), $e->getCode(), $e);
            }
        }

        // not fetching a specific register, get everything
        if (null === $reportingKey) {
            return $recordCollections;
        }

        // only return the specified registerCollection reporting Key
        foreach ($recordCollections as $collection) {
            if (strtolower($collection['reportingKey']) !== strtolower($reportingKey)) {
                // the requested register Ignoring...
                continue;
            }
            return $collection;
        }
        return null;
    }

    /**
     * Retrieve the metadata fields for the specified Register ID
     *
     * @param int $registerId
     * @return array|mixed
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getMetadataFields(int $registerId): mixed
    {
        $metadata = $this->cache->get(sprintf(self::METADATA_CACHE_KEY, $registerId));

        if (!isset($metadata)) {
            // This ought to be cacheable for a lot longer than student data
            try {
                $metadata = $this->api->request(
                    '/Main/api/registers/' . $registerId . '/metadata?expand=groups',
                    'GET',
                    token: $this->getToken(),
                );
                $this->cache->set(sprintf(self::METADATA_CACHE_KEY, $registerId), $metadata, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
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
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getStudentDateRanges(int $registerId, string $externalId): mixed
    {
        $dateRanges = $this->cache->get(sprintf(self::STUDENT_DATES_CACHE_KEY, $registerId, $externalId));

        if (!isset($dateRanges)) {
            // Request 1.2: find date ranges for student
            try {
                $dateRanges = $this->api->request(
                    '/Main/api/registers/' . $registerId . '/students/' . $externalId . '/dateranges',
                    'GET',
                    token: $this->getToken(),
                );
                $this->cache->set(sprintf(self::STUDENT_DATES_CACHE_KEY, $registerId, $externalId), $dateRanges, self::STUDENT_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
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
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getStudentFields(int $registerId, string $externalId): mixed
    {
        $studentFields = $this->cache->get(sprintf(self::STUDENT_FIELDS_CACHE_KEY, $registerId, $externalId));

        if (!isset($studentFields)) {
            // Request 1.3: find fields for student
            try {
                $studentFields = $this->api->request(
                    '/Main/api/registers/' . $registerId . '/students/' . $externalId . '/fields',
                    'GET',
                    token: $this->getToken(),
                );
                $this->cache->set(sprintf(self::STUDENT_FIELDS_CACHE_KEY, $registerId, $externalId), $studentFields, self::STUDENT_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
                // No record exists for this student: that's okay
                return [];
            }
        }
        return $studentFields['fields'];
    }

    /**
     * Retrieve the Data Source as specified by the Metadata Field
     * @param string $dataSourceUrl
     * @return array|mixed
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @see                      self::getMetadataFields
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getDataSource(string $dataSourceUrl): mixed
    {
        $dataSource = $this->cache->get(sprintf(self::DATASOURCE_FIELDS_CACHE_KEY, $dataSourceUrl));

        if (!isset($dataSource)) {
            try {
                $dataSource = $this->api->request(
                    $dataSourceUrl,
                    'GET',
                    token: $this->getToken(),
                );
                $this->cache->set(sprintf(self::DATASOURCE_FIELDS_CACHE_KEY, $dataSourceUrl), $dataSource, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
                // No metadata exists for this field: that's okay
                return [];
            }
        }
        return $dataSource['items'];
    }

    /****
     * debug options
     */

    /**
     *
     *
     * @param int    $registerId
     * @param string $externalId
     * @param int    $collectionId
     * @return array|mixed
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getRecordCollectionsRecords(int $registerId, string $externalId, int $collectionId)
    {
        $recordCollections = $this->cache->get(sprintf(self::RECORD_COLLECTION_STUDENT_CACHE_KEY, $registerId, $collectionId, $externalId));

        if (!isset($recordCollections)) {
            // This ought to be cacheable for a lot longer than student data
            try {
                $recordCollections = $this->api->request(
                    sprintf("/Main/api/registers/%s/students/%s/recordcollections/%s/records", $registerId, $externalId, $collectionId),
                    'GET',
                    token: $this->getToken(),
                );
                $this->cache->set(sprintf(self::RECORD_COLLECTION_STUDENT_CACHE_KEY, $registerId, $collectionId, $externalId), $recordCollections, self::STORAGE_CACHE_DEFAULT_TTL);
            } catch (ApiException $e) {
                // No metadata exists for this field: that's okay
                return  [];
            }
        }
        return $recordCollections['records'];
    }
}
