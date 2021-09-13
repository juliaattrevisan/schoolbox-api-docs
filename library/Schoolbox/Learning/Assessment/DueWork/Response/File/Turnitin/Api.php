<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

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
    private const HOST = 'schoolbox.tii-sandbox.com';


    /**
     * Turnitin version (TCA integration version)
     */
    private const VERSION = '3.2.4';


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
     * @var null
     */
    private $client = null;


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $apiKey
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host = $host;
        $this->apiKey = $apiKey;

        $this->initClient();
    }


    /**
     *
     */
    public function test()
    {
        $endpoint = self::HOST . self::TEST_ENDPOINT;

        /* Send it to the endpoint and see what comes back
         */
//        $this->client->request();

        /* Check errors
         */

        /* Log errors
         */
    }


    /**
     * Access the $endpoint provided
     *
     * $params is a structured array containing the parts necessary for
     * the given $endpoint, including the restful operation etc.
     *
     * @param string $endpoint
     * @param array  $params
     */
    public function retrieve(string $endpoint, array $params)
    {

        $endpoint = self::HOST . $endpoint;

        /* Send it to the endpoint and see what comes back
         */
//        $this->client->request();

        /* Check errors; if anything went wrong throw special types of exceptions
         * according to what happened. I found that in suds, we kind of started to
         * exceed the default \RuntimeException namespace for certain categories
         * of errors, and needed subclasses to better handle special circumstances
         */

        /* Log errors
         * Set state in the db to ERROR, of some kind
         */
    }


    /**
     * Manufacture a payload, as per turnitin's design
     * https://developers.turnitin.com/docs/tca#authentication
     *
     * These headers are required by turnitin for every request
     * so just set guzzle up for success
     */
    private function initClient()
    {
//        new Guzzle;
//
//        [
//            'X-Turnitin-Integration-Name',
//            'X-Turnitin-Integration-Version',
//            'Authorization',
//        ];
//
//        [
//            $this->host,
//            self::VERSION,
//            $this->apiKey,
//
//        ];

        /* Return a new guzzle setup
         */
        return;
    }
}