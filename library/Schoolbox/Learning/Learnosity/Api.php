<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use Learning\Learnosity\RateLimitException;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Request\DataApi;
use LearnositySdk\Request\Init;
use Psr\Log\LoggerInterface;

/**
 * Generates API calls to Learnosity.
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
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 5;


    /**
     * Jitter litter that gets appended to an exponential backoff
     * to kind of randomise when a request can be retried, to prevent
     * multiple requests inevitably occurring again at the same time
     * to cause another rate limit
     */
    private const JITTER_LOWER = 1;
    private const JITTER_UPPER = 3;


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
     * @throws \JsonException
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
     * @throws \JsonException
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
     * @param array $payload
     * @return mixed
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
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
     * @throws \Exception
     */
    private function dataRequest(string $host, string $endpoint, array $payload, string $actionType): mixed
    {
        /* Strip slashes, to prevent typos
         */
        $url = $host . trim($endpoint, '/');

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            try {
                $response = (new DataApi())->request(
                    $url,
                    [
                        'domain' => $this->serverName,
                        'consumer_key' => $this->consumerKey
                    ],
                    $this->consumerSecret,
                    $payload,
                    $actionType
                );

                $json = $response->json();

                if ($response->getStatusCode() === 429) {
                    /* Rate Limit exceeded; take a nap for a while before trying again
                     */
                    throw new RateLimitException();
                } elseif (null === $json || $response->getStatusCode() < 200 || $response->getStatusCode() > 202) {
                    /* There was some kind of error and you didn't get a valid json response
                     * The place where the json response would be contains some random text string instead
                     */
                    $errorPayload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

                    /* Some other unrecoverable error; retrying won't help
                     * Just surface this exception
                     */
                    throw new \RuntimeException(sprintf(
                        "%s %s: %s.\n\nHTTP %s %s",
                        $actionType,
                        $url,
                        $errorPayload['meta']['message'],
                        $response->getStatusCode(),
                        $response->getBody()
                    ));
                }

                /* No exceptions/rate limits encountered:
                 * leave with the data we requested
                 *
                 * Success!
                 */
                return $json;
            } catch (RateLimitException $e) {
                /* Rate limit reached; retry after napping
                 */
                $fails++;

                /* "some random jitter" is randomly between 1 and 3 sec by default
                 *
                 * Sleep for 3+1 seconds (2^0) + some random jitter
                 * then 3+2 seconds (2^1) + some random jitter
                 * then 3+4 seconds (2^2) + some random jitter
                 * then 3+8 seconds (2^3) + some random jitter
                 * then 3+16 seconds (2^4) + some random jitter
                 *
                 * then give up after multiple full rate limited cycles
                 */
                $napTime = self::NAP_TIMEOUT + 2^$fails + random_int(self::JITTER_LOWER, self::JITTER_UPPER);

                $this->logger->debug(sprintf(
                    'Rate limit reached for Data API: napping for %s seconds',
                    $napTime
                ));

                /* Nack to sleep Jeffrey
                 */
                sleep($napTime);

                continue;
            }
        }

        /* Couldn't recover
         */
        throw new \RuntimeException(sprintf(
            '%s %s failed after %s retries',
            $actionType,
            $url,
            self::NUM_RETRY
        ));
    }
}
