<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class Api
 *
 * Connects to the Tass endpoint, negotiates all the crypto stuff and returns a data
 * payload for the $method you provided. If it fails, it throws a Runtime exception -
 * it can fail when we can't decode the json data, or when we can't establish connectivity
 * etc. but it neds to throw Runtimes because Status and Specification use that.
 */
class Api implements IApi
{

    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * Tass API Version Number
     */
    private const VERSION_NUMBER = 3;


    /**
     * The host server
     *
     * @var string
     */
    private $host;


    /**
     * The dsn string of the Tass API server
     * This contains strings for companycode, appcode, token
     *
     * These are stored in the Endpoint Key field
     *
     * @var string
     */
    private $dsn;


    /**
     * @var TokenCodec
     */
    private $tokenCodec;


    /**
     *
     * @var array
     */
    private $credentials;


    /**
     * @var Client
     */
    private $client;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $dsn
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function __construct(string $host, string $dsn)
    {
        $this->host = $host;
        $this->dsn = $dsn;

        $this->client = $this->initClient($host, $dsn, self::DEFAULT_TIMEOUT);
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $dsn
     * @param int    $timeout
     * @return Client
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    private function initClient(string $host, string $dsn, int $timeout): Client
    {

        /* Figure out all the credential parts: company code, app code, token
         */
        $this->credentials = (new DSNCodec())->decode($dsn);

        /* Set up a token codec which does some security stuff based on the
         * params provided to its generateToken() method
         */
        $this->tokenCodec = new TokenCodec($this->credentials['token']);

        /* Set up a reusable guzzle
         */
        return new Client([
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'base_uri' => $host,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @param string $method
     * @param array  $params
     * @return array
     * @throws RuntimeException
     * @throws DomainException
     */
    public function retrieveDocument(string $method, array $params): ?array
    {
        $queryArgs = [
            'method'  => $method,
            'appcode' => $this->credentials['appcode'],
            'company' => $this->credentials['companycode'],
            'token'   => $this->tokenCodec->generateToken($params),
            'v'       => self::VERSION_NUMBER,
        ];

        $response = $this->client->get($this->host, [
            'query' => $queryArgs
        ]);

        /* Successful response from server, but it might not send a valid data payload back
         * so try to decode it. If it can't decode properly, return null
         */
        if ((int)$response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        if ($response) {
            throw new RuntimeException("Bad endpoint {$method}: {$response->getStatusCode()} {$response->getReasonPhrase()}");
        }

        /* Couldn't decode json, or tass server refused something, or something else died
         */
        throw new DomainException('Could not access Tass server, or bad json payload returned.');
    }
}
