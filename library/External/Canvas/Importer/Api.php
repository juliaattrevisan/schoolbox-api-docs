<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Schoolbox\External;

/**
 * Class Api
 *
 * Connects to the Canvas API, this is different to the Canvas DataSync Api
 */
class Api implements IApi
{
    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 30;


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes api endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 3;


    /**
     * @var string
     */
    private string $host;


    /**
     * @var Client $client
     */
    private Client $client;


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $bearerToken
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $bearerToken)
    {
        $this->host = $host;

        /* Authorization Bearer token
         * This is generated from inside the Canvas application itself
         */
        $this->client = $this->initClient($bearerToken, self::DEFAULT_TIMEOUT);
    }


    /**
     * Set up guzzle client
     *
     * @param string $bearerToken
     * @param int    $timeout
     * @return Client
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(string $bearerToken, int $timeout): Client
    {
        $headers = [
            'Authorization' => "Bearer {$bearerToken}",
            'Content-Type' => 'application/json'
        ];

        return new Client([
                              'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
                              'headers' => $headers,
                              'base_uri' => $this->host,
                              'timeout' => $timeout,
                              'http_errors' => false,
                          ]);
    }


    /**
     * Try accessing an endpoint with the $queryArgs provided
     *
     * @param string $method
     * @param string $route
     * @param array  $queryArgs
     * @return array
     * @throws RuntimeException
     * @throws \JsonException
     */
    public function retrieve(string $method, string $route, array $queryArgs): array
    {
        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $lastGuzzleError = '';
        $fails = 0;

        /* Try multiple times in case server dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             */
            try {
                $path = sprintf("%s/%s", $this->host, $route);

                $response = $this->client->request($method, $path, [
                    RequestOptions::JSON => $queryArgs
                ]);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                $lastGuzzleError = $e->getMessage();

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* check if it was a permission error,
             * if it was lets throw that now.
             * it may be the developer key is not set for the account
             * https://canvas.instructure.com/doc/api/file.developer_keys.html#how-do-management-features-function
             */
            if (401 === $response->getStatusCode()) {
                throw new RuntimeException(sprintf(
                                               'No Permission %s %s: %s. Payload: %s',
                                               $method,
                                               $response->getStatusCode(),
                                               $response->getReasonPhrase(),
                                               base64_encode(json_encode([
                                                                             'method' => $method,
                                                                             'route' => $route,
                                                                             'queryArgs' => $queryArgs
                                                                         ], JSON_THROW_ON_ERROR))
                                           ), $response->getStatusCode());
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                $contents = $response->getBody()->getContents();

                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
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
                 * Sometimes a request will succeed, sometimes it won't; there's no particular pattern
                 * just by looking at the log itself. In this circumstance, base64 the payload we
                 * tried to use, then pop it into an explorer to see what happened and to potentially
                 * replay the request
                 */
                throw new RuntimeException(sprintf(
                                               'Bad endpoint %s %s: %s %s after %s retries. Payload: %s',
                                               $method,
                                               $route,
                                               $response->getStatusCode(),
                                               $response->getReasonPhrase(),
                                               self::NUM_RETRY,
                                               base64_encode(json_encode([
                                                                             'method' => $method,
                                                                             'route' => $route,
                                                                             'queryArgs' => $queryArgs
                                                                         ], JSON_THROW_ON_ERROR))
                                           ));
            }
        }

        /* If you got here, something happened NUM_RETRY times trying to connect
         * and we couldn't even get a payload
         */
        throw new RuntimeException(sprintf(
                                       'Bad endpoint %s %s: could not establish connection to server after %s retries. %s',
                                       $method,
                                       $route,
                                       self::NUM_RETRY,
                                       $lastGuzzleError
                                   ));
    }
}
