<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

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
     * X-Turnitin-Integration-Name
     */
    private const INTEGRATION_FAMILY = 'Schoolbox';


    /**
     * Request connection timeout
     * Can't connect after this amount of seconds? You died
     */
    private const DEFAULT_CONNECT_TIMEOUT = 5;


    /**
     * Request timeout
     * Still going after this amount of seconds? You died
     */
    private const DEFAULT_TIMEOUT = 0;


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
     * Check that $version $host $apiKey were properly provided by using
     * Helper's isConfigured() check prior to instantiating.
     *
     * @param string $version
     * @param string $host
     * @param string $apiKey
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $version, string $host, #[SensitiveParameter] string $apiKey)
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
     * @return array|null
     * @throws Exception\BadEndpoint
     * @throws Exception\Credentials
     * @throws GuzzleException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function test(): ?array
    {
        /* Send it to the endpoint and see what comes back
         */
        return $this->retrieve('GET', self::TEST_ENDPOINT, []);
    }

    /**
     * Retrieve a raw response object for the method and endpoint provided.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function retrieveRawResponse(string $method, string $endpoint, array $params): ResponseInterface
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
                    'Content-Type'        => 'binary/octet-stream',
                    'Content-Disposition' => "inline; filename=\"{$filename}\""
                ]);

                /* This is actually a PUT request under the hood
                 * A special payload struct to facilitate this unique endpoint
                 */
                $result = $this->client->request('PUT', $endpoint, [
                    'headers'            => $headers,
                    RequestOptions::BODY => $params['contents']
                ]);

                break;

            /* Default case: assume standard headers
             * These seem sufficient for GET PUT POST types
             */
            default:
                $result = $this->client->request($method, $endpoint, [
                    'headers'            => $headers,
                    RequestOptions::JSON => $params
                ]);

                break;
        }
        return $result;
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
     * @throws Exception\Conflict
     * @throws Exception\Credentials
     * @throws GuzzleException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieve(string $method, string $endpoint, array $params): ?array
    {
        $result = $this->retrieveRawResponse($method, $endpoint, $params);

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
         * but turnitin sometimes hides error messages inside $result anyway
         */
        if (in_array($result->getStatusCode(), [400, 404])) {
            $info = json_decode($result->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            /* An error message might be found in either info[error] or info[message]
             * depending on whether you accessed a bad endpoint or the endpoint itself
             * is valid but is missing data, respectively
             *
             * e.g. an endpoint that is missing a report will throw 404 at you, despite
             * that endpoint being valid
             */
            throw new Exception\BadEndpoint(sprintf(
                'Turnitin: endpoint %s %s returned %s - %s',
                $method,
                $endpoint,
                $result->getStatusCode(),
                '' === $info['message'] ? $info['error'] : $info['message']
            ), 404);
        }

        /* 413 and 422 can fire out of "Upload Submission File Contents" but
         * our ValidFile() check should prevent those even being a possibility
         *
         * 409 occurs when you try to reuse a turnitin submission identifier that
         * something else already created
         */
        if (409 === $result->getStatusCode()) {
            throw new Exception\Conflict(
                sprintf(
                    'Turnitin: endpoint %s %s returned %s - %s',
                    $method,
                    $endpoint,
                    $result->getStatusCode(),
                    $result->getReasonPhrase()
                ),
                409
            );
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
        throw new Exception\Exception(sprintf(
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
            'connect_timeout' => self::DEFAULT_CONNECT_TIMEOUT,
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
