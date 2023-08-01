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
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\External;
use SensitiveParameter;

/**
 * Responsible for fetching data from canvas api
 * @deprecated
 */
class Connector
{
//    private const API_HOST = 'portal.inshosteddata.com';
    private const API_HOST = 'api.inshosteddata.com';

    /** array keys to get specific information from response */
    public const SCHEMA_VERSION = 'schemaVersion';

    /**
     * @param string          $apiKey
     * @param string          $apiSecret
     * @param LoggerInterface $logger
     * @param bool            $perform
     * @deprecated
     */
    public function __construct(#[SensitiveParameter] private string $apiKey, private string $apiSecret, private LoggerInterface $logger, private bool $perform)
    {
    }

    /**
     * Downloads data from CanvasData API and saves to local temp files
     * Moved from CLIImporter to easily be replaced with Data2Connector
     * @param string $tempDir
     * @param string $schemaFile
     * @return array
     */
    public function fetchCanvasDataFilesFromApi(string $tempDir, string $schemaFile): array
    {
        $dumpFile = $tempDir . '/dump.json';

        // Fetch latest dump
        $this->logger->notice('Getting latest data urls.');
        try {
            $dumpResponse = $this->fetchLatestDump();
        } catch (External\Exception $e) {
            $this->logger->warning('Could not get dump file.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $dump = json_decode($dumpResponse, true);
        $this->logger->notice("Getting schema version {$dump[$this::SCHEMA_VERSION]}.");

        try {
            $schemaResponse = $this->fetchSchema($dump[$this::SCHEMA_VERSION]);
        } catch (External\Exception $e) {
            $this->logger->warning('Could not fetch schema.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->storeTempFile($dumpResponse, $dumpFile);
        $this->storeTempFile($schemaResponse, $schemaFile);

        // Download data files
        $this->logger->notice('Downloading latest data.');
        return $dump['artifactsByTable'];
    }

    /**
     * Stores response to a file
     *
     * Will only overwrite a previously saved file if tool running in 'perform' mode
     *
     * @param string $response
     * @param string $path
     */
    private function storeTempFile(string $response, string $path): void
    {
        $this->logger->debug("Saving temp file to path '$path'");
        if ($this->perform || !file_exists($path)) {
            $fp = fopen($path, 'wb');
            fwrite($fp, $response);
            fclose($fp);
        }
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
     *
     * @see https://portal.inshosteddata.com/docs/api
     *
     * @param string $timestamp
     * @param string $path
     * @param array  $queryParams
     * @return string
     */
    private function getHmacSignature(string $timestamp, string $path, array $queryParams = []): string
    {
        ksort($queryParams);
        $parts = ['GET', self::API_HOST, '', '', $path, http_build_query($queryParams), $timestamp, $this->apiSecret];
        $message = implode("\n", $parts);
        return base64_encode(hash_hmac('sha256', $message, $this->apiSecret, true));
    }
}
