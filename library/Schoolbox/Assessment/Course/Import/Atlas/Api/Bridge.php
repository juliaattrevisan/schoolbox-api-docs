<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress\Redis\Cache as RedisCache;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception as HttpException;
use GuzzleHttp\Promise;
use Karriere\JsonDecoder\JsonDecoder;
use Predis\ClientInterface as RedisClient;
use Psr\Http\Message\ResponseInterface;

/**
 * Bridge between high-level Atlas API calls and their low-level implementation.
 *
 * Based against version 0.5.0 of the Atlas API.
 */
class Bridge
{
    private const CACHE_EXPIRY = 300;


    /**
     * @var HttpClient
     */
    private $httpClient;


    /**
     * @var RedisCache
     */
    private $redisCache;


    /**
     * @var JsonDecoder
     */
    private $decoder;


    /**
     * Constructor
     *
     * @param HttpClient  $httpClient
     * @param RedisClient $redisClient
     * @param JsonDecoder $decoder
     */
    public function __construct(HttpClient $httpClient, RedisClient $redisClient, JsonDecoder $decoder)
    {
        $this->httpClient = $httpClient;
        $this->redisCache = new RedisCache(
            function ($requests) {
                return $this->makeRequests($requests);
            },
            $redisClient,
            'atlasApi:' . INSTANCE_ID,
            self::CACHE_EXPIRY
        );
        $this->decoder = $decoder;
    }


    /**
     * Get a list of all curricula.
     *
     * @return Curriculum[]
     * @throws HttpException\ClientException for a 4xx error
     * @throws HttpException\ServerException for a 5xx error
     * @throws HttpException\RequestException for all other request errors
     */
    public function getCurriculumList(): array
    {
        // Can I cache this call?
        $requests = [
            'curriculumList' => ['curriculummap/'],
        ];
        $result   = $this->redisCache->get('curriculumList', [$requests]);
        return array_map(
            function ($assessment) {
                return $this->decoder->decodeArray($assessment, Curriculum::class);
            },
            $result['curriculumList']
        );
    }


    /**
     * Get a single curriculum and all of its contents in a single request.
     *
     * @param int  $curriculumId
     * @param bool $getAttachments Should attachments be retrieved?
     * @return Curriculum
     * @throws HttpException\ClientException for a 4xx error
     * @throws HttpException\ServerException for a 5xx error
     * @throws HttpException\RequestException for all other request errors
     */
    public function getCurriculum(int $curriculumId, bool $getAttachments): Curriculum
    {
        // Get all units, and their assessments/attachments in one shot
        $requests = [
            'curriculum' => ['curriculummap/' . $curriculumId],
        ];
        $result = $this->redisCache->get('curriculumUnits.' . $curriculumId, [$requests]);

        /** @var Curriculum $curriculum */
        $curriculum = $this->decoder->decodeArray(array_pop($result['curriculum']), Curriculum::class);
        $units      = $curriculum->getUnits();

        // For each unit: get its assessments and attachments
        $requests = [];
        foreach ($units as $unit) {
            $unitId = $unit->getId();
            $requests['assessments:' . $unitId] = ['assessment/', ['query' => ['unit_id' => $unitId]]];
            if ($getAttachments) {
                $requests['attachments:' . $unitId] = ['attachment/', ['query' => ['unit_id' => $unitId]]];
            }
        }

        // Put results into units
        $result = $this->redisCache->get('curriculumUnitData.' . $curriculumId, [$requests]);
        foreach ($units as $i => $unit) {
            $unitId = $unit->getId();

            // Load assessments and attachments into objects
            $assessments = $result['assessments:' . $unitId] ?? [];
            $unit->setAssessments(
                array_map(
                    function ($assessment) {
                        // No decodeMultipleArray method exists :|
                        // Also need to massage standards so they're not strings
                        $assessment['standards'] = array_map(
                            function ($v) {
                                return ['rawText' => $v];
                            },
                            $assessment['standards']
                        );
                        return $this->decoder->decodeArray($assessment, Assessment::class);
                    },
                    $assessments
                )
            );

            // Attachments is badly bugged, but can be worked around
            $attachments = isset($result['attachments:' . $unitId][0]) ? $result['attachments:' . $unitId] : [];
            $unit->setAttachments(
                array_map(
                    function ($attachment) {
                        return $this->decoder->decodeArray($attachment, Attachment::class);
                    },
                    $attachments
                )
            );
            $units[$i] = $unit;
        }

        // Try ordering responses by time frame.
        // I can only assume it's natcase-sortable, as there's no apparent
        // structure.
        usort(
            $units,
            function (Unit $a, Unit $b) {
                return strnatcasecmp($a->getTimeFrame(), $b->getTimeFrame());
            }
        );

        $curriculum->setUnits($units);
        return $curriculum;
    }


    /**
     * Makes all `$requests`, awaits their completion, and returns the results.
     *
     * @param array $requests
     * @param int   $currentPage
     * @return array
     * @throws HttpException\RequestException if a HTTP request fails
     */
    private function makeRequests(array $requests, $currentPage = 1): array
    {
        // Safeguard: no more than 10 pages
        if ($currentPage > 10) {
            return [];
        }

        // Asynchronously make the requests
        $asyncRequests = [];
        foreach ($requests as $requestName => $request) {
            $asyncRequests[$requestName] = \call_user_func_array([$this->httpClient, 'getAsync'], $request);
        }

        // Await request completion
        /** @noinspection PhpUnhandledExceptionInspection */
        $responses        = Promise\unwrap($asyncRequests);
        $result           = [];
        $nextPageRequests = [];
        foreach ($responses as $requestName => $response) {
            if (!$response instanceof ResponseInterface) {
                continue;
            }

            // Build the response, organising to get the next page if required.
            $decoded = json_decode($response->getBody()->getContents(), true);
            if ($currentPage < ($decoded['page']['last_page'] ?? 0)) {
                // Make a new request for the next page
                [$uri, $params] = $requests[$requestName];
                $params['query']['page']        = $currentPage + 1;
                $nextPageRequests[$requestName] = [$uri, $params];
            }

            // Assign the data; we're done
            $result[$requestName] = $decoded['data'] ?? [];
        }

        // Recursively run any next page requests;
        // merge their responses into the current data set
        if (!empty($nextPageRequests)) {
            foreach ($this->makeRequests($nextPageRequests, $currentPage + 1) as $requestName => $response) {
                $result[$requestName] = array_merge($result[$requestName] ?? [], $response);
            }
        }

        return $result;
    }
}
