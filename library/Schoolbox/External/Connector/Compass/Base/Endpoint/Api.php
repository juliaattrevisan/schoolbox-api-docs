<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class Api implements IApi
{

    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


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
    private const NUM_RETRY = 3;


    /**
     * External Ids are derived from this value
     *
     * @var string
     */
    private string $host;


    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     * @throws DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host = $host;

        $this->client = $this->initClient($host, $apiKey, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage();
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $dsn
     * @param int    $timeout
     * @return Client
     * @throws DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(string $host, string $dsn, int $timeout): Client
    {
        $credentials = (new Codec\DSN())->decode($dsn);

        $headers = [
            'CompassApiKey' => $credentials['key'],
            'Content-Type' => 'application/json'
        ];

        return new Client([
            'headers' => $headers,
            'timeout' => $timeout,
            'http_errors' => false,

            /* See https://docs.guzzlephp.org/en/stable/quickstart.html for usage
             * There's a little thing about relative urls here
             */
            'base_uri' => $host,
        ]);
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @param string $method
     * @param array  $params
     * @return array|null
     * @throws DomainException
     * @throws RuntimeException if the retrieval of the endpoint failed
     * @throws \JsonException
     */
    public function retrieveDocument(string $method, array $params): ?array
    {
        $response = null;

        /* Record endpoint usage
         */
        $this->apiUsage->record($method);

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             */
            try {
                $options = [];
                if (!empty($params)) {
                    $options[RequestOptions::JSON] = $params;
                }

                $response = $this->client->post($method, $options);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                try {
                    return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    /* Couldn't decode json, or server refused something, or something else died
                     * noop; just increase the $fails counter
                     */
                }
            }

            $fails++;
        }

        /* We tried multiple times and failed multiple times
         * This might as well have died
         */
        if ($response) {
            throw new RuntimeException(
                sprintf(
                    'Bad endpoint %s: %s %s after %s retries. Payload: %s',
                    $method,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    json_encode($params, JSON_THROW_ON_ERROR)
                ),
                $response->getStatusCode(),
                $e ?? null
            );
        }

        /* Couldn't decode json, or server refused something, or something else died
         * Guzzle repeatedly failed too many times and kept throwing exceptions when $response
         * was being requested, so I guess you ended up here
         */
        throw new DomainException('Could not access server, or bad json payload returned.', 0, $e ?? null);
    }


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }


    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }
}
