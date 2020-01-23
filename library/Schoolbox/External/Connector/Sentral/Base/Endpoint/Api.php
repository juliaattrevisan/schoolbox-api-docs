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
use UnexpectedValueException;
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
     * @param string $dsn
     * @param string $apiKey
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function __construct(string $dsn, string $apiKey)
    {
        $this->apiKey = $apiKey;

        $this->client = $this->initClient($dsn, $this->apiKey, self::DEFAULT_TIMEOUT);
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
     * @throws UnexpectedValueException
     */
    public function retrieveDocument(string $endpoint): array
    {

        /* Sentral's first/last/next pagination links are full urls containing the host
         * but for brevity we've set base_uri to the host already so we need to replace it away
         * every time we make a call to their pagination urls they provide to us ><
         * FIXME Tell Sydney James?
         */
        $response = $this->client->request('GET', str_replace($this->host, '', $endpoint));

        try {
            $decoded = json_decode($response->getBody()->getContents(), true);
        } catch (\RuntimeException $e) {
            throw new UnexpectedValueException('Sentral Endpoint error: could not json_decode payload; did something snap?');
        }

        /* When you can't decode the json, sentral's given you something unexpected
         * like an error string, or lost in transmission, etc.
         */
        if ($decoded === null) {
            throw new UnexpectedValueException('Sentral Endpoint error: could not json_decode payload; did something snap?');
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
            throw new UnexpectedValueException(sprintf(
                'Sentral Endpoint error: %s',
                "{$decoded['errors'][0]['title']} - {$decoded['errors'][0]['detail']}"
            ));
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
     * Have a look around
     *
     * @throws UnexpectedValueException
     */
    public function schemaExplorer(): void
    {
        $schema = $this->retrieveDocument('sentral/schema');

        foreach ($schema['paths'] as $path => $data) {
            new \Alaress_Probe('<hr>');

            $example = $data['get']['responses'][200]['content']['application/json']['examples']['validResponse']['value']['data'];

            if (null === $example) {
                new \Alaress_Probe($path, $data);
            } else {
                new \Alaress_Probe($path, $example);
            }
        }
    }
}
