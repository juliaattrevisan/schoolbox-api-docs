<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Alaress_Curl_Url;
use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;
use Zend\Uri\Exception;
use Zend\Uri\Uri;

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
     * The API key for this call, provided by the school
     *
     * @var string
     */
    private $apiKey;


    /**
     * @var Uri
     */
    private $url;


    /**
     * @var Client
     */
    private $client;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host   = $host;
        $this->apiKey = $apiKey;

        $this->client = $this->initClient($this->host, $this->apiKey, self::DEFAULT_TIMEOUT);
    }


    /**
     * Set up guzzle client
     *
     * @param string $dsn
     * @param string $key
     * @param int    $timeout
     * @return Client
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    private function initClient(string $dsn, string $key, int $timeout): Client
    {
        $credentials = (new DSNCodec())->decode($dsn);

        $headers = array_combine(
            ['X-API-KEY', 'X-API-TENANT'],
            [$key, $credentials['tenant']]
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
     * @throws DomainException
     */
    public function retrieveDocument(string $endpoint): array
    {
        $response = $this->client->request('GET', $endpoint);

        try {
            $decoded = json_decode($response->getBody()->getContents(), true);
        } catch (\RuntimeException $e) {
            throw new \DomainException('Could not json_decode payload; did something snap?');
        }

        /* When you can't decode the json, sentral's given you something unexpected
         * like an error string, or lost in transmission, etc.
         */
        if ($decoded === null) {
            throw new \DomainException('Could not json_decode payload in sentral api; did something snap?');
        }

        return $decoded;
    }


    /**
     * Cache a url definition then return its value
     *
     * @return Uri
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    public function getUrl(): Uri
    {
        /* TODO Set Path
         */
        if (!$this->url) {
            $this->url = (new Uri())
                ->setScheme('https')
                ->setHost($this->host)
                ->setPath('')
                ->setQuery(['apikey' => $this->apiKey]);
        }

        return $this->url;
    }


    /**
     * Set up curl instance
     *
     * @return Alaress_Curl_Url
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    private function initCurl(): \Alaress_Curl_Url
    {
        return new Alaress_Curl_Url($this->getUrl());
    }
}
