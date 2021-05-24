<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Api implements IApi
{

    /**
     * Default pagination limit - can go between 0 and 200
     *
     * It's not formally used in this class yet because you need to extra-futz with the $endpoint
     * in order to facilitate this value as a querystring param
     *
     * It's pretty easy to forget it exists though, and we'll probably need to fiddle with this
     * at some point in the future.
     */
    private const DEFAULT_LIMIT = 12;


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * The host server
     *
     * @var string
     */
    private $host;


    /**
     * @var Client
     */
    private $client;


    /**
     * @var ApiUsage
     */
    private $apiUsage;


    /**
     * Constructor
     *
     * @param string $dsn
     * @param string $apiKey
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function __construct(string $dsn, string $apiKey)
    {
        $this->client = $this->initClient($dsn, $apiKey, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage();
    }


    /**
     * Set up guzzle client
     *
     * @param string $dsn
     * @param string $key
     * @param int    $timeout
     * @return Client
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    private function initClient(string $dsn, string $key, int $timeout): Client
    {
        $credentials = (new DSNCodec())->decode($dsn);

        /* Remember the actual host value
         */
        $this->host = $credentials['host'];

        $headers = array_combine(
            ['X-API-KEY', 'X-API-TENANT', 'X-API-OPTIONS'],
            [$key, $credentials['tenant'], 'map-include,inline-include']
        );

        return new Client([
            'headers' => $headers,
            'base_uri' => $credentials['host'],
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @param string $endpoint
     * @return array
     * @throws InvalidArgumentException
     */
    public function retrieveDocument(string $endpoint): array
    {

        /* Urls come in mixed formats; some have a version number,
         * some have a host in front of it. Accessing endpoints in a
         * consistent manner means removing all this junk.
         *
         * Remove the host prefix from the endpoint
         * Remove the version number from the endpoint
         */
        $endpointFiltered = str_replace($this->host, '', $endpoint);
        $endpointFiltered = preg_replace('#^\/v1\/#', '', $endpointFiltered);

        /* Record endpoint usage
         */
        $this->apiUsage->record($endpointFiltered);

        $response = $this->client->request('GET', $endpointFiltered);

        try {
            $decoded = json_decode($response->getBody()->getContents(), true);
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException(sprintf(
                'Sentral Endpoint error: could not json_decode payload for endpoint %s; did something snap?',
                $endpoint
            ));
        }

        /* When you can't decode the json, sentral's given you something unexpected
         * like an error string, or lost in transmission, etc.
         */
        if ($decoded === null) {
            throw new InvalidArgumentException(sprintf(
                'Sentral Endpoint error: could not json_decode payload for endpoint %s - it is null; did something snap?',
                $endpoint
            ));
        }

        /* Check for error payload instead of data payload
         */
        if (array_key_exists('errors', $decoded)) {
            /* Error Handling - sometimes their messages are unhelpful, but at least they return
             * it in a synonymous data structure. There might be multiple errors (I haven't seen how)
             * so we'll just return the first one in the list for starters
             *
             * Anyway, errors might get thrown if your host, tenant or key is incorrect
             *   - The host should end with a trailing slash
             */
            throw new InvalidArgumentException(sprintf(
                'Sentral Endpoint error at endpoint %s: %s',
                $endpoint,
                "{$decoded['errors'][0]['title']} - {$decoded['errors'][0]['detail']}"
            ));
        }

        return $decoded;
    }


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }
}
