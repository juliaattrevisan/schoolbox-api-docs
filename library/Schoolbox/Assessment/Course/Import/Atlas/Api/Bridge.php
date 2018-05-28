<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress\Redis\Cache as RedisCache;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
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
     * Constructor
     *
     * @param HttpClient  $httpClient
     * @param RedisClient $redisClient
     */
    public function __construct(HttpClient $httpClient, RedisClient $redisClient)
    {
        $this->httpClient = $httpClient;
        $this->redisCache = new RedisCache(
            function ($requests) { return $this->makeRequests($requests); },
            $redisClient,
            'atlasApi',
            self::CACHE_EXPIRY
        );
    }


    /**
     * Get a list of all curricula.
     *
     * @return array
     * @throws \Throwable
     */
    public function getCurriculumList(): array
    {
        // Can I cache this call?
        $requests = [
            'curriculumList' => ['curriculummap/'],
        ];
        $result = $this->redisCache->get('curriculumList', [$requests]);
        return $result['curriculumList'];
    }


    /**
     * Get a single curriculum and all of its contents in a single request.
     *
     * @param int $curriculumId
     * @return array
     * @throws \Throwable
     */
    public function getCurriculum(int $curriculumId): array
    {
        // Get all units, and their assessments/attachments in one shot
        $requests = [
            'curriculum' => ['curriculummap/'.$curriculumId],
        ];

        $result     = $this->redisCache->get('curriculumUnits.'.$curriculumId, [$requests]);
        $curriculum = array_pop($result['curriculum']);
        $units      = $curriculum['units'];

        // For each unit: get its assessments and attachments
        $requests = [];
        foreach ($units as $unit) {
            $requests['assessments:'.$unit['id']] = ['assessment/', ['query' => ['unit_id' => $unit['id']]]];
            $requests['attachments:'.$unit['id']] = ['attachment/', ['query' => ['unit_id' => $unit['id']]]];
        }

        // Put results into units
        $result = $this->redisCache->get('curriculumUnitData.'.$curriculumId, [$requests]);
        foreach ($units as $i => $unit) {
            $unitId = $unit['id'];
            $unit['assessments'] = $result['assessments:'.$unitId] ?? [];
            // Attachments is badly bugged, but can be worked around
            $unit['attachments'] = isset($result['attachments:'.$unitId][0]) ? $result['attachments:'.$unitId] : [];
            $units[$i] = $unit;
        }

        // Try ordering responses by time frame.
        // I can only assume it's natcase-sortable, as there's no apparent
        // structure.
        usort(
            $units,
            function ($a, $b) {
                return strnatcasecmp($a['time_frame'], $b['time_frame']);
            }
        );

        $curriculum['units'] = $units;
        return $curriculum;
    }


    /**
     * Makes all `$requests`, awaits their completion, and returns the results.
     *
     * @param array $requests
     * @param int   $currentPage
     * @return array
     * @throws \Throwable
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
        $responses        = Promise\unwrap($asyncRequests);
        $result           = [];
        $nextPageRequests = [];
        foreach ($responses as $requestName => $response) {
            if (!$response instanceof ResponseInterface) {
                continue;
            }

            // Build the response, organising to get the next page if required.
            $decoded = json_decode($response->getBody()->getContents(), true);
            if ($currentPage < $decoded['page']['last_page']) {
                // Make a new request for the next page
                [$uri, $params] = $request;
                $params['query']['page'] = $currentPage + 1;
                $nextPageRequests[$requestName] = [$uri, $params];
            }

            // Assign the data; we're done
            $result[$requestName] = $decoded['data'];
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
