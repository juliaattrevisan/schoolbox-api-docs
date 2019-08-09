<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\External\Exception;
use function count;
use InvalidArgumentException;

/**
 * Responsible for fetching data from canvas into staging database
 */
class DataFetcher
{

    private const API_HOST = 'portal.inshosteddata.com';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var string */
    private $tempDir;


    /**
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $tempDir
     * @throws InvalidArgumentException
     */
    public function __construct(string $apiKey, string $apiSecret, string $tempDir)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->tempDir = $tempDir;
    }

    /**
     * @throws Exception
     */
    public function fetch(): string
    {
        $schemaFile = "{$this->tempDir}/schema.json";
        $response = $this->executeCanvasDataCmd('/schema/latest');
        $fp = fopen($schemaFile, 'wb');
        fwrite($fp, $response);
        fclose($fp);
        return $schemaFile;
    }

    /**
     * @param string $command
     * @return string
     * @throws Exception
     */
    private function executeCanvasDataCmd(string $command): string
    {
        $timestamp = date(DATE_RFC7231);
        $url = 'https://' . self::API_HOST . '/api' . $command;

        // Generate signature
        $hmac = $this->hmac_signature($timestamp, '/api' . $command);

        $client = new Client();
        $options = [
            'Authorization' => 'HMACAuth ' . $this->apiKey . ':' . $hmac,
            'Date' => $timestamp,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = $client->request('GET', $url, $options);
        } catch (GuzzleException $e) {
            throw new Exception('Unable to get:' . $e->getMessage());
        }
        return $response->getBody()->getContents();
    }

    /**
     * Generate HMAC signature
     * @param $timestamp
     * @param $path
     * @param string $queryString
     * @return string
     */
    private function hmac_signature($timestamp, $path, $queryString = '')
    {
        $query = '';
        if (!empty($queryString)) {
            $params = explode('&', $queryString);
            sort($params);
            $query = implode('&', $params);
        }
        $parts = array('GET', self::API_HOST, 'application/json', '', $path, $query, $timestamp, $this->apiSecret);
        $message = implode("\n", $parts);
        return base64_encode(hash_hmac('sha256', $message, $this->apiSecret, TRUE));
    }
}
