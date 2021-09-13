<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception\BadEndpointException;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception\CredentialsException;


/**
 * Establish connectivity to Turnitin
 */
class Api
{

    /**
     * Endpoint used to test the connectivity
     *
     * Uses the "Get Features Enabled" endpoint, as per
     * https://developers.turnitin.com/turnitin-core-api/best-practice/exposing-tca-settings
     */
    private const TEST_ENDPOINT = '/api/v1/features-enabled';


    /**
     * I think each school will have their own?
     */
    private const TEST_HOST = 'schoolbox.tii-sandbox.com';


    /**
     * X-Turnitin-Integration-Name
     */
    private const INTEGRATION_FAMILY = 'Schoolbox';


    /**
     * Turnitin version (TCA integration version)
     */
    private const VERSION = '3.2.4';


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * The school's api host at turnitin
     * (The default schoolbox test api host is schoolbox.tii-sandbox.com)
     *
     * @var string
     */
    private string $host;


    /**
     * The API key we stored in config; if it's empty, don't do anything
     * Turnitin isn't properly configured/enabled
     *
     * @var string
     */
    private string $apiKey;


    /**
     * Guzzle setup
     *
     * @var Client null
     */
    private Client $client;


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $apiKey
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host = $host;
        $this->apiKey = $apiKey;

        /* Set up guzzle client
         */
        $this->client = $this->initClient();
    }


    /**
     * Use the Get Features Enabled endpoint to see if we connect, as per
     * https://developers.turnitin.com/turnitin-core-api/best-practice/exposing-tca-settings
     *
     * @throws \DomainException
     * @throws \DebugBar\DebugBarException
     */
    public function test(): bool
    {
        /* Send it to the endpoint and see what comes back
         */
        try {
            $this->retrieve('GET', self::TEST_ENDPOINT, []);

            /* Throws exceptions if something went wrong, normally...
             * but how do you make this endpoint fail?
             */
            return true;
        } catch (BadEndpointException|CredentialsException|GuzzleException $e) {
            // noop: all errors return false
        }

        /* You Failed
         */
        return false;
    }


    /**
     * Access the $endpoint provided
     *
     * $params is a structured array containing the parts necessary for
     * the given $endpoint, including the restful operation etc.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     * @return Response
     * @throws Exception\BadEndpointException
     * @throws Exception\CredentialsException
     * @throws \DebugBar\DebugBarException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \DomainException
     */
    public function retrieve(string $method, string $endpoint, array $params): Response
    {
        $endpoint = "{$this->host}{$endpoint}";

        /* Send it to the endpoint and see what comes back
         */
        $result = $this->client->request($method, $endpoint, [
            RequestOptions::QUERY => $params
        ]);

        /* Check errors; if anything went wrong throw special types of exceptions
         * according to what happened. I found that in suds, we kind of started to
         * exceed the default \RuntimeException namespace for certain categories
         * of errors, and needed subclasses to better handle special circumstances
         */

        /* Can't authenticate
         */
        if (403 === $result->getStatusCode()) {
            throw new Exception\CredentialsException();
        }

        /* Bad endpoint
         */
        if (404 === $result->getStatusCode()) {
            throw new Exception\BadEndpointException();
        }

        /* I guess we succeeded, which should be the case most of the time,
         * but we should check for other errors in the caller function to decide
         * what happens next and how those errors might be logged/presented to users
         */
        if (200 === $result->getStatusCode()) {
            return $result;
        }

        /* Arbitrary default case for now
         * Something occurred that we haven't accounted for just now
         *
         * Can develop this futher as per suds tass, to remember payloads etc.
         * for diagnostics
         */
        throw new \DomainException('Turnitin retrieve() failed');
    }


    /**
     * Manufacture a payload, as per turnitin's design
     * https://developers.turnitin.com/docs/tca#authentication
     *
     * These headers are required by turnitin for every request
     * so just set guzzle up for success
     *
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(): Client
    {
        $headers = [
            'X-Turnitin-Integration-Name' => self::INTEGRATION_FAMILY,
            'X-Turnitin-Integration-Version' => self::VERSION,
            'Authorization' => "Bearer {$this->apiKey}",
        ];

        /* Return a new guzzle setup
         */
        return new Client([
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'headers' => $headers,
            'base_uri' => $this->host,
            'timeout' => self::DEFAULT_TIMEOUT,
            'http_errors' => false,
        ]);
    }
}