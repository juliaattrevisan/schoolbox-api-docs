<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard;

use GuzzleHttp\Client;

/**
 * Class Api
 *
 * @package Schoolbox\Clipboard
 */
class Api implements IApi
{

    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $authorizationToken
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $authorizationToken)
    {
        $this->client = $this->initClient($host, $authorizationToken, self::DEFAULT_TIMEOUT);
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $authorizationToken
     * @param int    $timeout
     * @return Client
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(string $host, string $authorizationToken, int $timeout): Client
    {
        $headers = [
            'Authorization' => "Bearer: {$authorizationToken}"
        ];

        return new Client([
            'headers' => $headers,
            'base_uri' => $host,
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
     */
    public function retrieve(string $method, string $endpoint, array $params = []): array
    {
        // TODO: Implement retrieve() method.
    }
}