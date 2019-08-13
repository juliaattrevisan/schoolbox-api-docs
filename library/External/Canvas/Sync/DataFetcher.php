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
use Psr\Log\LoggerInterface;
use Schoolbox\External\Exception;
use function count;

/**
 * Responsible for fetching data from canvas into staging database
 */
class DataFetcher
{

//    private const API_HOST = 'portal.inshosteddata.com';
    private const API_HOST = 'api.inshosteddata.com';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var string */
    private $tempDir;

    /** @var LoggerInterface */
    private $log;

    /**
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $tempDir
     * @param LoggerInterface $log
     */
    public function __construct(string $apiKey, string $apiSecret, string $tempDir, LoggerInterface $log)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->tempDir = $tempDir;
        $this->log = $log;
    }

    /**
     * @throws Exception
     */
    public function fetchSchema(): string
    {
        $schemaFile = "{$this->tempDir}/schema.json";
        $response = $this->executeCanvasDataCmd('/api/schema/latest');
        $fp = fopen($schemaFile, 'wb');
        fwrite($fp, $response);
        fclose($fp);
        return $schemaFile;
    }

    /**
     * Gets all urls to data
     * @throws Exception
     */
    public function fetchDataUrl(): string
    {
        $schemaFile = "{$this->tempDir}/data.json";
//        $response = $this->executeCanvasDataCmd('/api/account/self/dump', ['limit' => 1]);
        $response = $this->executeCanvasDataCmd('/api/account/self/file/latest');
        $fp = fopen($schemaFile, 'wb');
        fwrite($fp, $response);
        fclose($fp);
        return $schemaFile;
    }

    /**
     * Downloads data files
     * @param $file
     */
    public function downloadDataFiles($file)
    {
        $fileList = json_decode(file_get_contents($file), true);
        $tables = $fileList['artifactsByTable'];
        $client = new Client();
        foreach ($tables as $tableName => $tableData) {
            if (!count($tableData['files'])) {
                continue;
            }
            $tableFile = $tableData['files'][0];
            $tableDataFile = fopen($this->tempDir . '/' . $tableName . '.gz', 'w');
            try {
                $response = $client->get($tableFile['url'], ['save_to' => $tableDataFile]);
                $this->log->debug("downloading $tableName to $tableDataFile");
            } catch (GuzzleException $e) {
                $this->log->warning("Failed to download $tableDataFile: " . $e->getMessage());
            }
        }
    }


    /**
     * @param string $command
     * @param array $queryParams
     * @return string
     * @throws Exception
     */
    private function executeCanvasDataCmd(string $command, array $queryParams = []): string
    {
        $timestamp = gmdate(DATE_RFC7231);
        $url = 'https://' . self::API_HOST . $command;

        // Generate signature
        $hmac = $this->hmac_signature($timestamp, $command, $queryParams);

        $client = new Client();
        $options = [
            'Authorization' => 'HMACAuth ' . $this->apiKey . ':' . $hmac,
            'Date' => $timestamp,
        ];

        try {
            $response = $client->request('GET', $url, [RequestOptions::HEADERS => $options, RequestOptions::QUERY => $queryParams]);
        } catch (GuzzleException $e) {
            throw new Exception('Unable to get:' . $e->getMessage());
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
    private function hmac_signature($timestamp, $path, $queryParams = [])
    {
        ksort($queryParams);
        $parts = ['GET', self::API_HOST, '', '', $path, http_build_query($queryParams), $timestamp, $this->apiSecret];
        $message = implode("\n", $parts);
        return base64_encode(hash_hmac('sha256', $message, $this->apiSecret, TRUE));
    }
}
