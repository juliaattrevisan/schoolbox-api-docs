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
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Exception\Credentials;

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
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * The current schoolbox instance version
     * Turnitin does something with this, idk what, though
     *
     * @var string
     */
    private string $version;


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
    public function __construct(string $version, string $host, string $apiKey)
    {
        $this->host = $host;
        $this->apiKey = $apiKey;
        $this->version = $version;

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
        } catch (BadEndpoint | Credentials | GuzzleException $e) {
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
     * @return array|null
     * @throws Exception\BadEndpoint
     * @throws Exception\Credentials
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieve(string $method, string $endpoint, array $params): ?array
    {
        $endpoint = "{$this->host}{$endpoint}";

        /* Generate a standard header set
         */
        $headers = $this->generateDefaultHeaders();

        /* Send it to the endpoint and see what comes back
         */
        switch ($method) {
            /* TODO Uploading a binary specifically
             *  Uses a special content-type header value
             */
            case 'UPLOAD':
                /* Add some extra fields and values, specific to purpose
                 */
                $filename = urlencode($params['filename']);

                $headers = array_merge($headers, [
                    'Content-Type' => 'binary/octet-stream',
                    'Content-Disposition' => "inline; filename=\"{$filename}\""
                ]);

                /* This is actually a PUT request under the hood
                 * A special payload struct to facilitate this unique endpoint
                 */
                $result = $this->client->request('PUT', $endpoint, [
                    'headers' => $headers,
                    RequestOptions::BODY => $params['contents']
                ]);

                break;

            /* Default case: assume standard headers
             * These seem sufficient for GET PUT POST types
             */
            default:
                $result = $this->client->request($method, $endpoint, [
                    'headers' => $headers,
                    RequestOptions::JSON => $params
                ]);

                break;
        }

        /* Check errors; if anything went wrong throw special types of exceptions
         * according to what happened. I found that in suds, we kind of started to
         * exceed the default \RuntimeException namespace for certain categories
         * of errors, and needed subclasses to better handle special circumstances
         */

        /* Can't authenticate
         */
        if (403 === $result->getStatusCode()) {
            throw new Exception\Credentials(sprintf(
                'Turnitin: bad access credentials for %s; check your payload',
                $endpoint
            ), 403);
        }

        /* Bad endpoint
         */
        if (404 === $result->getStatusCode()) {
            throw new Exception\BadEndpoint(sprintf(
                'Turnitin: endpoint %s not found',
                $endpoint
            ), 404);
        }

        /* I guess we succeeded, which should be the case most of the time,
         * but we should check for other errors in the caller function to decide
         * what happens next and how those errors might be logged/presented to users
         */
        if (\in_array($result->getStatusCode(), [200, 201, 202], true)) {
            return json_decode($result->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        /* Arbitrary default case for now
         * Something occurred that we haven't accounted for just now
         *
         * Can develop this further as per suds tass, to remember payloads etc.
         * for diagnostics
         */
        throw new \DomainException(sprintf(
            "Turnitin failed to retrieve(): %s %s - %s %s",
            $method,
            $endpoint,
            $result->getStatusCode(),
            $result->getReasonPhrase()
        ), $result->getStatusCode());
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
        return new Client([
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'base_uri' => $this->host,
            'timeout' => self::DEFAULT_TIMEOUT,
            'http_errors' => false,
        ]);
    }


    /**
     * Default headers required for all api requests
     *
     * All requests require application/json as Content Type
     * except for the one where you upload binary data
     * (that uses binary/octet-stream)
     *
     * @return string[]
     */
    private function generateDefaultHeaders(): array
    {
        return [
            'X-Turnitin-Integration-Name' => self::INTEGRATION_FAMILY,
            'X-Turnitin-Integration-Version' => $this->version,
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }
}
