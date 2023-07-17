<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use DebugBar\DebugBarException;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Request\DataApi;
use LearnositySdk\Request\Init;

/**
 * Generates API calls to Learnosity.
 *
 * FIXME at the moment it just generates initOptions for their
 * embedded javascript app, but might expand to facilitate the
 * other endpoints as needed.
 */
class Api
{
    private const DATA_API_HOST = 'https://data.learnosity.com/v2023.1.LTS/';


    /**
     * Constructor.
     *
     * @param string      $serverName
     * @param string      $consumerKey
     * @param string      $consumerSecret
     * @param Logger|null $logger
     */
    public function __construct(
        private string $serverName,
        #[\SensitiveParameter] private string $consumerKey,
        #[\SensitiveParameter] private string $consumerSecret,
        private ?Logger $logger = null
    ) {
    }


    /**
     * This class contains sensitive information: therefore, its debug
     * info should be cleared.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [];
    }


    /**
     * Manufacture a payload to initialise Learnosity javascript app
     *
     * @param string $service
     * @param array  $payload
     * @return string|array
     * @throws \JsonException
     * @throws ValidationException
     */
    public function initOptions(string $service, array $payload): string|array
    {
        /* Optionally log our payloads to learnosity
         */
        $this->logger?->debug(sprintf(
            'Accessed service %s with payload %s',
            $service,
            json_encode($payload, JSON_THROW_ON_ERROR)
        ));

        return (new Init(
            $service,
            [
                'domain'       => $this->serverName,
                'consumer_key' => $this->consumerKey
            ],
            $this->consumerSecret,
            $payload
        ))->generate();
    }


    /**
     * Make a specific request to the Data API in Learnosity
     *
     * @param string $endpoint
     * @param array  $payload
     * @param string $method
     * @return mixed
     * @throws ValidationException
     */
    public function dataRequest(string $endpoint, array $payload, string $method = 'get'): mixed
    {
        /* Strip slashes, to prevent typos
         */
        $response = (new DataApi())->request(
            self::DATA_API_HOST . trim($endpoint, '/'),
            [
                'domain'       => $this->serverName,
                'consumer_key' => $this->consumerKey
            ],
            $this->consumerSecret,
            $payload,
            $method
        );

        return $response->json();
    }
}
