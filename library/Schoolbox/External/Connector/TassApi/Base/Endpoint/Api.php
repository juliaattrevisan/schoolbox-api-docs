<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use Alaress_Curl_Url;
use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
use Zend\Uri\Exception;
use Zend\Uri\Uri;

/**
 * Class Api
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
    private const VERSION_NUMBER = 2;


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
     * @var Uri
     */
    private $url;


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

        /* Couldn't decode json, or tass server refused something
         */
        throw new RuntimeException('Could not access Tass server, or bad json payload returned');
    }
}
