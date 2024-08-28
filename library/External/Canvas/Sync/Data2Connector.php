<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Url\Uri;

/**
 * Responsible for fetching data from canvas api
 */
class Data2Connector
{
    private const API_HOST = 'api-gateway.instructure.com';

    // Attempt to check data preparation jobs every minute for 20 times
    // number of times to try checking job status
    private const MAX_JOB_CHECK_COUNTS = 20;
    // seconds to wait for the job to finish
    private const RETRY_TIME_PERIOD = 60;

    /**
     * Guzzle requests consts
     */
    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const ATTEMPT_BASE_TIMEOUT = 3;

    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const MAX_ATTEMPTS = 3;

    private ?string $accessToken = null;

    /**
     * Domain or product, hard coding this to canvas for now.
     */
    private string $namespace = 'canvas';

    /**
     * @var array<string, string> table name to job id
     */
    private array $jobs = [];

    /**
     * @var array<string, array> table name to urls
     */
    private array $urls = [];

    private Client $client;

    /**
     * @param string          $apiSecret
     * @param string          $clientId
     * @param LoggerInterface $logger
     */
    public function __construct(private string $apiSecret, private string $clientId, private LoggerInterface $logger)
    {
        $this->client = new Client();
    }

    /**
     * Downloads data from CanvasData API and saves to local temp files
     *
     * Loops through all tables, for each table it will:
     * first spawns jobs to generate a presigned url
     * then once that job is finished
     * download all the files for that table
     *
     * Moved from CLIImporter to easily be replaced with Data2Connector
     * @param array  $tablesToImport
     * @param string $tempDir
     * @param bool   $perform
     * @return void
     */
    public function fetchCanvasDataFilesFromApi(array $tablesToImport, string $tempDir, bool $perform = false): void
    {
        $this->logger->notice('Getting latest data urls.');

        $promises = [];
        $jitter     = new Jitter(new FloatGenerator(), self::MAX_ATTEMPTS, self::ATTEMPT_BASE_TIMEOUT);
        $logger = $this->logger;
        $client = new Client([
                                 'timeout' => self::DEFAULT_TIMEOUT,
                             ]);

        try {
            $tableNames = $this->fetchTableNames();
            foreach ($tableNames as $table) {
                if (!in_array($table, $tablesToImport)) {
                    continue;
                }
                $this->logger->debug(sprintf("Fetching table: %s", $table));

                $this->fetchTableData($table);

                // poll for job to finish
                $jobCheckCount = 0;
                while ($jobCheckCount < self::MAX_JOB_CHECK_COUNTS && !empty($this->jobs[$table])) {
                    $this->logger->debug(sprintf('Sleeping %d seconds before re-fetching.', self::RETRY_TIME_PERIOD));
                    sleep(self::RETRY_TIME_PERIOD);
                    $this->fetchJobData($table, $this->jobs[$table]);
                    $jobCheckCount++;
                }
                // when jobs for this table are complete
                // download jobs async
                // there can be multiple files
                $tableFiles = $this->urls[$table]['files'];
                foreach ($tableFiles as $index => $tableFile) {
                    $fileName      = "$tempDir/$table.$index.gz";
                    $tableDataFile = fopen(($perform || !file_exists($fileName)) ? $fileName : '/dev/null', 'wb');
                    $this->logger->debug("Downloading $table to $fileName");

                    $promise = $client->getAsync($tableFile['url'], ['sink' => $tableDataFile]);

                    /* Any exception thrown by guzzle might as well be a reason to nap and retry
                     */
                    $promise->then(
                        null,
                        function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                            // retry download...
                            $attempt = 0;
                            $wait = (int)$jitter->calculateJitter($attempt);
                            $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                            usleep($wait * 1000);
                            return $promise;
                        }
                    )->then(
                        null,
                        function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                            // retry download...
                            $attempt = 1;
                            $wait = (int)$jitter->calculateJitter($attempt);
                            $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                            usleep($wait * 1000);
                            return $promise;
                        }
                    )->then(
                        null,
                        function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                            // retry download...
                            $attempt = 2;
                            $wait = (int)$jitter->calculateJitter($attempt);
                            $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                            usleep($wait * 1000);
                            return $promise;
                        }
                    )->then(
                        null,
                        function (RequestException $e) use ($fileName): void {
                            // epic fail, just exit...
                            // If fallen through to here, there was an error
                            throw new \Schoolbox\Application\Exception\Exception(
                                sprintf("Failed to download %s: #3 times. Error:%s", $fileName, $e->getMessage())
                            );
                        }
                    );
                    $promises[] = $promise;
                }


                // Now wait until all the files for that table are downloaded
                $bulkPromise = \GuzzleHttp\Promise\Utils::all($promises);
                $result      = $bulkPromise->wait();

                // move onto the next table
            }
        } catch (ExternalException $e) {
            $this->logger->warning('Could not get dump file.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
    }

    /**
     * @see https://data-access-platform-api.s3.amazonaws.com/index.html#tag/API/paths/~1query~1{namespace}~1table~1{table}~1schema/get
     * @throws ExternalException
     */
    public function fetchTableSchema(string $tableName): array
    {
        $response = $this->executeCanvasDataCmd('GET', "/query/$this->namespace/table/$tableName/schema");
        return $response['schema'];
    }

    /**
     * @return array
     * @throws ExternalException
     */
    private function fetchTableNames(): array
    {
        $response = $this->executeCanvasDataCmd('GET', "/query/$this->namespace/table");
        return $response['tables'];
    }

    /**
     * Triggers dumping table data
     * @see https://data-access-platform-api.s3.amazonaws.com/index.html#tag/API/paths/~1query~1{namespace}~1table~1{table}~1data/post
     *
     * Completed = {array[6]}
     *  id = "19316116-1dba-471e-a649-5d2aa38fed6f"
     *  status = "complete"
     *  objects = {array[1]}
     *   0 = {array[1]}
     *       id = "19316116-1dba-471e-a649-5d2aa38fed6f/part-00000-d889df8b-5b27-49c3-aa48-41a69007b2c2-c000.tsv.gz"
     *       expires_at = "2023-07-28T00:03:03Z"
     *       schema_version = {int} 1
     *       at = "2023-07-26T22:22:00Z"
     *
     *  Not completed = {string[3]}
     *   id = "c7379dd0-5902-4851-a5cf-a37ac8296ef2"
     *   status = "waiting"
     *   expires_at = "2023-07-28T02:56:26Z"
     *
     * @param string $table
     * @return void
     * @throws ExternalException
     */
    public function fetchTableData(string $table): void
    {
        $response = $this->executeCanvasDataCmd('POST', "/query/$this->namespace/table/$table/data", ['format' => 'tsv']);
        $this->handleJobResponse($response, $table);
    }

    /**
     * Check status of job
     * https://data-access-platform-api.s3.amazonaws.com/index.html#tag/API/paths/~1job~1{id}/get
     * @param string $table
     * @param string $jobId
     * @return void
     */
    public function fetchJobData(string $table, string $jobId): void
    {
        try {
            $response = $this->executeCanvasDataCmd('GET', "/job/$jobId");
            $this->handleJobResponse($response, $table);
        } catch (ExternalException $e) {
            $this->logger->warning(sprintf('Failed to fetch job status: table:%s jobId:%s %s', $table, $jobId, $e->getTraceAsString()));
        }
    }


    /**
     * Fetches data download url
     * @see https://data-access-platform-api.s3.amazonaws.com/index.html#tag/API/paths/~1object~1url/post
     * @param string $objectId
     * @return array
     * @throws ExternalException
     */
    public function fetchUrl(string $objectId): array
    {
        $response = $this->executeCanvasDataCmd('POST', "/object/url", [['id' => $objectId]]);
        /**
         * Expecting ['url' => url string]
         */
        return $response['urls'][$objectId];
    }


    /**
     * Executes a CanvasData API call and returns the response body
     *
     * @param string $method
     * @param string $command
     * @param array  $formParams
     * @return array
     * @throws ExternalException
     */
    private function executeCanvasDataCmd(string $method, string $command, array $formParams = []): array
    {
        $headers = [
            'x-instauth' => $this->getAccessToken(),
        ];
        if ($method === 'POST') {
            $headers['Content-Type'] = 'application/json';
            $headers['Accept']       = 'application/json';
            $options                 = [
                RequestOptions::HEADERS => $headers,
                RequestOptions::BODY    => json_encode($formParams),
            ];
        } else {
            $options = [
                RequestOptions::HEADERS     => $headers,
                RequestOptions::FORM_PARAMS => $formParams,
            ];
        }
        $url = (new Uri())->withScheme('https')->withHost(self::API_HOST)->withPath('/dap' . $command);
        // authenticate
        try {
            $response = $this->client->request($method, (string)$url, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new ExternalException("Unable to get $url: {$e->getMessage()}");
        }
    }

    /**
     * Generates access token
     *
     * @return string
     * @throws ExternalException
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        $auth       = base64_encode(sprintf('%s:%s', $this->clientId, $this->apiSecret));
        $options    = [
            'Authorization' => "Basic $auth",
        ];
        $url        = 'https://' . self::API_HOST . '/ids/auth/login';
        $formParams = ['grant_type' => 'client_credentials'];
        // authenticate
        try {
            $response          = $this->client->request(
                'POST',
                $url,
                [
                    RequestOptions::HEADERS     => $options,
                    RequestOptions::FORM_PARAMS => $formParams,
                ]
            );
            $content           = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $content['access_token'];
            return $content['access_token'];
        } catch (GuzzleException $e) {
            throw new ExternalException("Unable to get $url: {$e->getMessage()}");
        }
    }

    /**
     * Check Job status:
     * @param array  $response
     * @param string $table
     * @return void
     * @throws ExternalException
     */
    private function handleJobResponse(array $response, string $table): void
    {
        $status = $response['status'];
        switch ($status) {
            case 'waiting':
            case 'running':
                $this->jobs[$table] = $response['id'];
                $this->logger->debug("Data preparation for $table (job:{$response['id']}) is {$status}");
                return;
            case 'complete':
                unset($this->jobs[$table]);
                $this->urls[$table]['files'] ??= [];
                foreach ($response['objects'] as $objectData) {
                    $url                           = $this->fetchUrl($objectData['id']);
                    $this->urls[$table]['files'][] = $url;
                    $this->logger->debug("Data preparation for $table completed: {$url['url']}");
                }
                return;
            case 'failed':
                $this->logger->warning(sprintf('Failed to prepare download for %s', $table));
        }
    }
}
