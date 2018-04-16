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
        $client = $this->buildClient();

        // Get all units, and their assessments/attachments in one shot
        $requests = [
            'curriculum' => $client->getAsync('curriculummap/'.$curriculumId),
            'units'      => $client->getAsync(
                'unit/',
                ['query' => ['curriculummap_id' => $curriculumId]]
            ),
        ];

        $result     = $this->unwrapRequests($requests);
        $curriculum = array_pop($result['curriculum']);
        $units      = $result['units'];

        // For each unit: get its assessments and attachments
        $requests = [];
        foreach ($units as $unit) {
            $requests['assessments:'.$unit['id']] = $client->getAsync(
                'assessment/',
                ['query' => ['unit_id' => $unit['id']]]
            );
            $requests['attachments:'.$unit['id']] = $client->getAsync(
                'attachment/',
                ['query' => ['unit_id' => $unit['id']]]
            );
        }

        // Put results into units
        $result = $this->unwrapRequests($requests);
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
     * Awaits all `$requests` to finish, and returns their results.
     *
     * @param array|ResponseInterface[] $requests
     * @return array
     * @throws \Throwable
     */
    private function unwrapRequests(array $requests): array
    {
        return array_map(
            function (ResponseInterface $response) {
                return json_decode($response->getBody()->getContents(), true)['data'];
            },
            Promise\unwrap($requests)
        );
    }
}
