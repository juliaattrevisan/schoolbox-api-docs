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
     * Default Edlink API base URI; used when no host override is provided.
     */
    private const DEFAULT_BASE_URI = 'https://ed.link/api/v2/graph/';

    /**
     * API path appended to the configured host.
     */
    private const API_PATH = '/api/v2/graph/';

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
     * The base uri used for stripping endpoint prefixes in ApiUsage.
     */
    private string $baseUri = '';

    /**
     * Cache the decoded credentials for diagnostics.
     *
     * @var array
     */
    private array $credentials = [];

    /**
     * Constructor.
     *
     * @param string $host   Optional base host override. Empty string falls back to ed.link.
     * @param string $apiKey The DSN-encoded credentials (token=...).
     * @param int    $timeout
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $apiKey,
        private int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->client = $this->initClient($this->host, $this->apiKey, $this->timeout);
        $this->apiUsage = new ApiUsage($this->baseUri);
    }

    /**
     * Set up guzzle client
     *
     * @throws \DomainException
     */
    private function initClient(string $host, #[SensitiveParameter] string $dsn, int $timeout): Client
    {
        $this->credentials = (new Codec\DSN())->decode($dsn);

        /* Host defaults to ed.link when the admin hasn't set one.
         * When an override is supplied (staging / self-hosted), append the
         * standard API path.
         */
        $host = trim($host);
        $this->baseUri = '' === $host
            ? self::DEFAULT_BASE_URI
            : rtrim($host, '/') . self::API_PATH;

        return new Client([
            'base_uri' => $this->baseUri,
            'timeout' => $timeout,
            'http_errors' => false,
            'headers' => [
                'Authorization' => "Bearer {$this->credentials['token']}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     * @throws \DomainException
     * @throws \JsonException
     */
    public function retrieve(string $method, string $endpoint, array $params = []): array
    {
        $response = null;
        $fails = 0;
        $e = null;
        while ($fails < self::NUM_RETRY) {
            try {
                $options = [];

                if (!empty($params)) {
                    $options[RequestOptions::QUERY] = $params;
                }

                $start = microtime(true);
                $response = $this->client->request($method, $endpoint, $options);
                $end = microtime(true);

                $this->apiUsage->record($endpoint, $end - $start);
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

    /**
     * Get decoded DSN pieces.
     *
     * @return array
     */
    public function getCredentials(): array
    {
        return $this->credentials;
    }
}
