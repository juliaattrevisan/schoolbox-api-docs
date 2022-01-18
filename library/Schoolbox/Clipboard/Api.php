<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;


/**
 * Class Api
 *
 * @package Schoolbox\Clipboard
 */
class Api implements IApi
{

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
     * Request timeout
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
     * @param string $host
     * @param string $authorizationToken
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $authorizationToken)
    {
        $this->client = $this->initClient($host, $authorizationToken, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage();
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
            'Authorization' => "Bearer {$authorizationToken}",
            'Content-Type' => 'application/json'
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
     * @throws \RuntimeException
     * @throws \DomainException
     * @throws \JsonException
     */
    public function retrieve(string $method, string $endpoint, array $params = []): array
    {
        $response = null;

        /* Record endpoint usage
         */
        $this->apiUsage->record($endpoint);

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
                    $options[RequestOptions::QUERY] = $params;

                    /* Debugging:
                     * Find out what guzzle calculated as the endpoint + query string params
                     */
                    $options['on_stats'] = function (TransferStats $stats) use (&$url) {
                        $url = $stats->getEffectiveUri();
                    };
                }

                $response = $this->client->request($method, $endpoint, $options);

                /* Debugging:
                 * Find out what guzzle calculated as the endpoint + query string params
                 */
                print_r($url);
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
                    $content = $response->getBody()->getContents();

                    if ('' === $content) {
                        return [];
                    }

                    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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
            throw new \RuntimeException(
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
