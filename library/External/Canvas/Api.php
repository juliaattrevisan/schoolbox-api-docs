<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas;

use GuzzleHttp\Client;
use RuntimeException;
use DomainException;
use Schoolbox\External;

/**
 * Class Api
 *
 * Connects to the Canvas API, this is different to the Canvas DataSync Api
 */
class Api
{
    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes tass endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */

    private const NUM_RETRY = 3;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var string */
    private $host;

    /** @var Client $client */
    private Client $client;

    /**
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $host
     */
    public function __construct(string $apiKey, string $apiSecret, string $host)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->host = $host;

        // build the guzzle client
        $this->client = $this->initClient(self::DEFAULT_TIMEOUT);
    }

    /**
     * Set up guzzle client
     * @param int $timeout
     * @return Client
     */
    private function initClient($timeout): Client
    {
        return new Client([
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'base_uri' => $this->host,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }

    /**
     * Try accessing an enpoint with the $queryArgs provided
     *
     * @param string $method
     * @param string  $route
     * @param array  $queryArgs
     * @return mixed|void
     * @throws DomainException
     * @throws RuntimeException
     * @throws \JsonException
     */
    public function tryPayload(string $method, string $route, array $queryArgs)
    {
        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $lastGuzzleError = '';
        $fails = 0;

        /* Try multiple times in case tass dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             *
             * I don't think tass plays in the same reality as most other people
             * and they might broadcast error states in a successful payload one day
             */
            try {
                $path = sprintf("%s/%s", $this->host, $route);
                $response = $this->client->$method($path, [
                    'query' => $queryArgs
                ]);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                $lastGuzzleError = $e->getMessage();

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            /* We got a response but it's broken
             * Take a little nap before trying again
             * (We suspect that it might be timing related, or network traffic)
             */
            $fails++;

            sleep(self::NAP_TIMEOUT);

            /* We tried multiple times and failed multiple times
             * This might as well have died
             *
             * Exit by throwing exception like we normally would have done
             * otherwise we'll get stuck in an infinite loop and pound the api
             */
            if ($fails >= self::NUM_RETRY) {
                /* Some schools intermittently report a Bad Endpoint in a non-repeating fashion
                 * Sometimes a guy will succeed, sometimes he won't; there's no particular pattern
                 * just by looking at the log itself. In this circumstance, base64 the payload we
                 * tried to use, then pop it into the explorer to see what happened and to replay
                 * the access attempt
                 */
                throw new RuntimeException(sprintf(
                    'Bad endpoint %s: %s %s after %s retries. Payload: %s',
                    $method,
                    $response->getStatusCode(),
                    self::NUM_RETRY,
                    $response->getReasonPhrase(),
                    base64_encode(json_encode([
                         /* These were requested by suds
                          */
                         'method' => $method,
                         'route' => $route,

                         /* This was generated and sent to tass
                          */
                        'queryArgs' => $queryArgs
                    ], JSON_THROW_ON_ERROR))
                ));
            }
        }

        /* If you got here, something happened NUM_RETRY times trying to connect
         * and we couldn't even get a payload
         */
        throw new RuntimeException(sprintf(
            'Bad endpoint %s: could not establish connection to server after %s retries. %s',
            $method,
            self::NUM_RETRY,
            $lastGuzzleError
        ));
    }
}
