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

        $this->client = $this->initClient($dsn, $dsn, self::DEFAULT_TIMEOUT);
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

//        $queryArgs = [
//            'method'  => $method,
//            'appcode' => $this->appCode,
//            'company' => $this->companyCode,
//            'v'       => self::VERSION_NUMBER,
//            'token'   => $this->tokenBuilder->generateToken($params),
//        ];


    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @return string
     */
    public function retrieveDocument(): string
    {
        return '';
    }
}
