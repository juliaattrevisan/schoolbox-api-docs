<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use SensitiveParameter;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class Api implements IApi
{
    /**
     * Edlink API base URL (hardcoded — no self-hosted option)
     */
    private const BASE_URI = 'https://ed.link/api/v2/graph/';

    /**
     * Number of seconds to wait before retrying a failed endpoint
     */
    private const NAP_TIMEOUT = 3;

    /**
     * Number of retry attempts before giving up
     */
    private const NUM_RETRY = 3;

    /**
     * Request timeout in seconds
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * Constructor.
     *
     * @param string $token The Edlink integration access token
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(#[SensitiveParameter] string $token)
    {
        $this->client = $this->initClient($token, self::DEFAULT_TIMEOUT);
        $this->apiUsage = new ApiUsage(self::BASE_URI);
    }

    /**
     * Set up guzzle client
     *
     * @param string $token
     * @param int    $timeout
     * @return Client
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(#[SensitiveParameter] string $token, int $timeout): Client
    {
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return new Client([
            'headers' => $headers,
            'base_uri' => self::BASE_URI,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    /**
     * @inheritDoc
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     * @return array
     * @throws \RuntimeException
     * @throws \DomainException
     * @throws \JsonException
     */
    public function retrieve(string $method, string $endpoint, array $params = []): array
    {
        $response = null;

        $this->apiUsage->record($endpoint);

        $fails = 0;

        while ($fails < self::NUM_RETRY) {
            try {
                $options = [];

                if (!empty($params)) {
                    $options[RequestOptions::QUERY] = $params;
                }

                $response = $this->client->request($method, $endpoint, $options);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            if ($response->getStatusCode() === 200) {
                try {
                    $content = $response->getBody()->getContents();

                    if ('' === $content) {
                        return [];
                    }

                    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    /* Couldn't decode json; increase $fails counter
                     */
                }
            }

            $fails++;

            sleep(self::NAP_TIMEOUT);
        }

        if ($response) {
            throw new \RuntimeException(
                sprintf(
                    'Bad endpoint %s %s: %s %s after %s retries. Body: %s. Payload: %s',
                    $method,
                    $endpoint,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    $response->getBody()->getContents(),
                    json_encode($params, JSON_THROW_ON_ERROR)
                ),
                $response->getStatusCode(),
                $e ?? null
            );
        }

        throw new \DomainException('Could not access server, or bad json payload returned.', 0, $e ?? null);
    }

    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }
}
