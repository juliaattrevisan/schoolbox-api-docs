<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Request\DataApi;
use LearnositySdk\Request\Init;
use Psr\Log\LoggerInterface;

/**
 * Generates API calls to Learnosity.
 *
 * FIXME at the moment it just generates initOptions for their
 * embedded javascript app, but might expand to facilitate the
 * other endpoints as needed.
 */
class Api
{
    /**
     * From Kit, 25/9/2023
     * Use the Oregon West area for writes, specifically.
     *
     * "Some services, such as Authoring, do not support regions; therefore, their endpoints
     * do not include a region. For Item Authoring endpoints these will point to the Oregon
     * region. All content creation (writes) should go through the Data API - Oregon West
     * endpoint. For reads, you should use the region that your consumer is set up in (for
     * lower latency and hence maximum speed). Note: the region used to read should always
     * match the consumer’s region, not the end user’s region."
     *
     * https://help.learnosity.com/hc/en-us/articles/360000754798-What-Regions-and-Endpoints-Should-I-Use-#us-west-oregon
     */
    private const DATA_API_HOST_READ = 'https://data-au.learnosity.com/v2023.2.LTS/';
    private const DATA_API_HOST_WRITE = 'https://data-or.learnosity.com/v2023.2.LTS/';


    /**
     * Constructor.
     *
     * @param string      $serverName
     * @param string      $consumerKey
     * @param string      $consumerSecret
     * @param Logger|null $logger
     * @throws \DomainException
     */
    public function __construct(
        private string $serverName,
        #[\SensitiveParameter] private string $consumerKey,
        #[\SensitiveParameter] private string $consumerSecret,
        private ?LoggerInterface $logger = null
    ) {
        /* If not set, I guess loudly announce it
         */
        if ('' === $consumerKey || '' === $consumerSecret) {
            throw new \DomainException('Learnosity is not configured; please check settings');
        }
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
     * Alias for ActionType "get" endpoints
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function dataGetRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest(self::DATA_API_HOST_READ, $endpoint, $payload, 'get');
    }


    /**
     * Alias for ActionType "set" endpoints
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function dataSetRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest(self::DATA_API_HOST_WRITE, $endpoint, $payload, 'set');
    }


    /**
     * Alias for ActionType "update" endpoints
     *
     * @param string $endpoint
     * @param array  $payload
     * @return mixed
     * @throws ValidationException
     */
    public function dataUpdateRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest(self::DATA_API_HOST_WRITE, $endpoint, $payload, 'update');
    }


    /**
     * Make a specific request to the Data API in Learnosity
     *
     * $actionType corresponds to Action Type in their docs;
     * Learnosity is *not* a RESTful api.
     *
     * @param string $host
     * @param string $endpoint
     * @param array $payload
     * @param string $actionType
     * @return mixed
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function dataRequest(string $host, string $endpoint, array $payload, string $actionType): mixed
    {
        /* Strip slashes, to prevent typos
         */
        $url = $host . trim($endpoint, '/');

        $response = (new DataApi())->request(
            $url,
            [
                'domain'       => $this->serverName,
                'consumer_key' => $this->consumerKey
            ],
            $this->consumerSecret,
            $payload,
            $actionType
        );

        $json = $response->json();

        /* There was some kind of error and you didn't get a valid json response
         * The place where the json response would be contains some random text string instead
         */
        if (null === $json || $response->getStatusCode() < 200 || $response->getStatusCode() > 202) {
            $errorPayload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            throw new \RuntimeException(sprintf(
                "%s %s: %s.\n\nHTTP %s %s",
                $actionType,
                $url,
                $errorPayload['meta']['message'],
                $response->getStatusCode(),
                $response->getBody()
            ));
        }

        return $json;
    }
}
