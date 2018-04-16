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
        $units = json_decode(
            $client->get(
                'unit/',
                ['query' => ['curriculummap_id' => $curriculumId]]
            )->getBody()->getContents(),
            true
        )['data'];

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
        $result = array_map(
            function (ResponseInterface $response) {
                return json_decode($response->getBody()->getContents(), true)['data'];
            },
            Promise\unwrap($requests)
        );

        // Put results into units
        foreach ($units as $i => $unit) {
            $unitId = $unit['id'];
            $unit['assessments'] = $result['assessments:'.$unitId] ?? [];
            // Attachments is badly bugged, but can be worked around
            $unit['attachments'] = isset($result['attachments:'.$unitId][0]) ? $result['attachments:'.$unitId] : [];
            $units[$i] = $unit;
        }

        $this->getResponse()->content->set($this->buildJsonOutput($units));
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
}
