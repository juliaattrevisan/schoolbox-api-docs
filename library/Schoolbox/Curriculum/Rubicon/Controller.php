<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Rubicon;

use Alaress\Web\Controller\Base;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * Rubicon Atlas controller.
 *
 * So far, contains only a test page.
 */
class Controller extends Base
{
    /**
     * Test page for Atlas Rubicon
     */
    public function getTestPage(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@curriculumRubicon/testPage.twig',
                [
                    // Test token
                    'token' => $this->getConfigLoader()->get('rubicon_api_token'),
                ]
            )
        );
    }

    /**
     * Proxy for calls to Rubicon's API.
     *
     * @param string $endpoint
     */
    public function getProxy(string $endpoint): void
    {
        // pass thru GET params
        $params = [
            'query' => $this->getRequest()->query->getArrayCopy(),
        ];
        unset($params['query']['page']);

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                json_decode($this->buildClient()->get($endpoint, $params)->getBody()->getContents())
            )
        );
    }

    /**
     * Test for getting a curriculum and contents in a single request.
     *
     * @param int $curriculumId
     * @throws \Throwable
     */
    public function getCurriculum(int $curriculumId): void
    {
        // Get all units, and their assessments/attachments in one shot
        $requests = [
            'curriculum' => ['curriculummap/'.$curriculumId, []],
            'units'      => ['unit/',                        ['query' => ['curriculummap_id' => $curriculumId]]]
        ];

        $result     = $this->makeRequests($requests);
        $curriculum = array_pop($result['curriculum']);
        $units      = $result['units'];

        // For each unit: get its assessments and attachments
        $requests = [];
        foreach ($units as $unit) {
            $requests['assessments:'.$unit['id']] = ['assessment/', ['query' => ['unit_id' => $unit['id']]]];
            $requests['attachments:'.$unit['id']] = ['attachment/', ['query' => ['unit_id' => $unit['id']]]];
        }

        // Put results into units
        $result = $this->makeRequests($requests);
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
        $this->getResponse()->content->set($this->buildJsonOutput($curriculum));
    }

    /**
     * Helper for building a Rubicon API client
     *
     * @return Client
     */
    private function buildClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.rubicon.com/api/',
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->getConfigLoader()->get('rubicon_api_token'),
                'Accept'        => 'application/json',
            ],
        ]);
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
        $client        = $this->buildClient();
        foreach ($requests as $requestName => $request) {
            $asyncRequests[$requestName] = \call_user_func_array([$client, 'getAsync'], $request);
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
