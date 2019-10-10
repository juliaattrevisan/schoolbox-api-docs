<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\External;

/**
 * Responsible for fetching data from canvas api
 */
class Connector
{

//    private const API_HOST = 'portal.inshosteddata.com';
    private const API_HOST = 'api.inshosteddata.com';

    /** array keys to get specific information from response */
    public const SCHEMA_VERSION = 'schemaVersion';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /**
     * @param string $apiKey
     * @param string $apiSecret
     */
    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }


    /**
     * @return string
     * @throws External\Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function fetchLatestDump(): string
    {
        return $this->executeCanvasDataCmd('/api/account/self/file/latest');
    }


    /**
     * @param string $version
     * @return string
     * @throws External\Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function fetchSchema(string $version): string
    {
        return $this->executeCanvasDataCmd("/api/schema/$version");
    }


    /**
     * Executes a CanvasData API call and returns the response body
     *
     * @param string $command
     * @param array  $queryParams
     * @return string
     * @throws External\Exception
     * @throws RuntimeException if unable to read or an error occurs while reading.
     * @throws InvalidArgumentException If Guzzle client instantiated with invalid handler
     */
    private function executeCanvasDataCmd(string $command, array $queryParams = []): string
    {
        $timestamp = gmdate(DATE_RFC7231);
        $url = 'https://' . self::API_HOST . $command;

        // Generate signature
        $hmac = $this->getHmacSignature($timestamp, $command, $queryParams);

        $client = new Client();
        $options = [
            'Authorization' => "HMACAuth {$this->apiKey}:$hmac",
            'Date' => $timestamp,
        ];

        try {
            $response = $client->request('GET', $url, [RequestOptions::HEADERS => $options, RequestOptions::QUERY => $queryParams]);
        } catch (GuzzleException $e) {
            throw new External\Exception("Unable to get $url: {$e->getMessage()}");
        }
        return $response->getBody()->getContents();
    }

    /**
     * Generates HMAC signature
     * @param $timestamp
     * @param $path
     * @param array $queryParams
     * @return string
     */
    private function getHmacSignature($timestamp, $path, $queryParams = []): string
    {
        ksort($queryParams);
        $parts = ['GET', self::API_HOST, '', '', $path, http_build_query($queryParams), $timestamp, $this->apiSecret];
        $message = implode("\n", $parts);
        return base64_encode(hash_hmac('sha256', $message, $this->apiSecret, true));
    }
}
