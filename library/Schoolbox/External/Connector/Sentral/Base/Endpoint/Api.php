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
use SensitiveParameter;

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
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes sentral endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     *
     * Actual example is curl error 35, something to do with certs
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
     * The host server
     *
     * @var string
     */
    private string $host;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;


    /**
     * @var array
     */
    private array $options = [];


    /**
     * Constructor
     *
     * @param string $host
     * @param string $key
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function __construct(string $host, #[SensitiveParameter] string $key)
    {
        $this->client = $this->initClient($host, $key, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage();
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $key
     * @param int    $timeout
     * @return Client
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    private function initClient(string $host, #[SensitiveParameter] string $key, int $timeout): Client
    {
        /* Remember the actual host value
         *
         * Check trailing slash in the host
         * Without it, you just get Generic Error in a valid payload struct
         * This enforces a trailing slash so your host is always in the right format
         */
        $this->host = rtrim($host, '/') . '/';

        /* Dig out the key and tenant values
         */
        $dsnParts = (new Codec\DSN())->decode($key);

        /* Remember the optional pieces we offer
         */
        $this->options = $dsnParts['options'] ?? [];

        $headers = [
            'X-API-KEY' => $dsnParts['key'],
            'X-API-TENANT' => $dsnParts['tenant'],
            'X-API-OPTIONS' => 'map-include,inline-include'
        ];

        return new Client([
            'headers' => $headers,
            'base_uri' => $this->host,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @param string $endpoint
     * @return array
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function retrieveDocument(string $endpoint): array
    {
        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $lastGuzzleError = '';
        $fails = 0;

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

        /* Try multiple times in case sentral dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            try {
                $response = $this->client->request('GET', $endpointFiltered);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                $lastGuzzleError = $e->getMessage();

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            try {
                $decoded = json_decode($response->getBody()->getContents(), true);
            } catch (\RuntimeException $e) {
                throw new InvalidArgumentException(sprintf(
                    'Sentral Endpoint error: could not json_decode payload for endpoint %s - malformed payload from Sentral',
                    $endpoint
                ), 400);
            }

            /* When you can't decode the json, sentral's given you something unexpected
             * like an error string, or lost in transmission, etc.
             */
            if ($decoded === null) {
                throw new InvalidArgumentException(sprintf(
                    'Sentral Endpoint error: could not json_decode payload for endpoint %s - null payload from Sentral',
                    $endpoint
                ), 400);
            }

            /* Multiple meanings of 404 that aren't necessarily sensible
             */
            if (404 === $response->getStatusCode()) {
                /* Person doesn't have a registered timeview
                 * Occurs when you try access someone's timetable, but they don't have one in sentral
                 * That also stupidly comes out as a 404 in an error struct
                 *
                 * "Staff member was found but has no registered timeview information"
                 * "Missing timeview student information for the given student"
                 */
                if (strpos($decoded['errors'][0]['detail'], ' timeview ') > -1) {
                    throw new \DomainException(sprintf(
                        'Sentral Endpoint error at endpoint %s: %s',
                        $endpoint,
                        "{$decoded['errors'][0]['title']} - {$decoded['errors'][0]['detail']}"
                    ));
                }

                /* Endpoint doesn't exist
                 */
                throw new InvalidArgumentException(sprintf(
                    'Sentral Endpoint error at endpoint %s: %s',
                    $endpoint,
                    "{$decoded['errors'][0]['title']} - {$decoded['errors'][0]['detail']}"
                ), 404);
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
                ), 400);
            }

            return $decoded;
        }

        /* We got here because we never returned $decoded
         * We got here because $fails exceeded a number of retries
         */
        throw new InvalidArgumentException(sprintf(
            'Sentral Endpoint error at endpoint %s: could not establish connection to server after %s retries. %s',
            $endpoint,
            self::NUM_RETRY,
            $lastGuzzleError
        ));
    }

    /**
     * @param string $endpoint
     * @return string
     */
    public function retrievePortrait(string $endpoint): string
    {
        /* Mine out an error message if you try to access a thing that doesn't exist
        */
        $lastGuzzleError = '';
        $fails = 0;

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

        /* Try multiple times in case sentral dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            try {
                $response = $this->client->request('GET', $endpointFiltered);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                $lastGuzzleError = $e->getMessage();

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            return $response->getBody()->getContents();
        }
    }

    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
