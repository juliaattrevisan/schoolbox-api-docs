<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RetryMiddleware;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\AccessToken\Generator as AccessTokenGenerator;
use SensitiveParameter;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class Api implements IApi
{
    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 3;


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * Maximum number of pages to obtain from an endpoint
     * to safeguard against infinite loops
     */
    private const MAX_PAYLOADS = 100;


    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * A Veracross Access Token generator, in case you needed to
     * make a new one somewhere along the way
     *
     * @var AccessTokenGenerator
     */
    private AccessTokenGenerator $accessTokenGenerator;


    /**
     * The base uri that consists of host and school identifier
     * Paginated data can appear separately in ApiUsage
     *
     * @var string
     */
    private string $baseUri = '';


    /**
     * Cache the decoded credentials here, because other parts of the veracross
     * connector needs something from here to build attendance links etc.
     *
     * @var array
     */
    private array $credentials = [];


    /**
     * Constructor.
     *
     * @param string $host
     * @param string $apiKey
     * @param int $timeout
     * @param bool $deepLog
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $apiKey,
        private int $timeout = self::DEFAULT_TIMEOUT,
        private bool $deepLog = false,
    ) {
        $this->client = $this->initClient($this->host, $this->apiKey, $this->timeout);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage($this->baseUri);
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $dsn
     * @param int $timeout
     * @param callable $decider
     * @return Client
     */
    private function initClient(string $host, #[SensitiveParameter] string $dsn, int $timeout): Client
    {
        /* You need these parts when build attendance links
         */
        $this->credentials = (new Codec\DSN())->decode($dsn);

        /* Enforce slash in the endpoint prefix
         *
         * Remember this, because we need to remove it in ApiUsage
         * for it to report unique endpoints properly
         *
         * Ensure this baseUri ends in just one /
         */
        $this->baseUri = sprintf(
            '%s/%s/v3/',
            rtrim($host, '/'),
            $this->credentials['schoolroute'],
        );

        /* Access Token Generator
         *
         * Note that schools can configure an authentication host closer to their geographic location
         * THe default is https://accounts.veracross.com but Aussies will use https://accounts.veracross.au
         *
         * This auth host is different to School Data Endpoint Host.
         */
        $this->accessTokenGenerator = new AccessTokenGenerator(
            $this->credentials['schoolroute'],
            $this->credentials['clientid'],
            $this->credentials['clientsecret'],
            $this->credentials['authhost'],
            $this->deepLog
        );

        /* TODO How to implement this? Our retrymiddleware doesn't have a factory() method lol
         */
//        $stack = HandlerStack::create();
//        $stack->push(RetryMiddleware::factory());

        /* Use guzzle's Retry middleware
         * https://github.com/caseyamcl/guzzle_retry_middleware
         */
        return new Client([
            'base_uri' => $this->baseUri,
            'timeout' => $timeout,
            'http_errors' => false,
            'max_retry_attempts' => 3,
            'retry_on_timeout' => true,
            'on_retry_callback' => static function(
                int $attemptNumber,
                float $delay,
                /* This is Psr\Http\Message\RequestInterface but we don't have that
                 */
                &$request,
                array &$options,
                /* This is Psr\Http\Message\ResponseInterface but we don't have that
                 */
                $response,
                ?\Throwable $exception
            ) {
                /* I've never seen this happen, but want to see it happen.
                 */
                echo sprintf(
                    "Retrying request to %s. Server responded with %s. Will wait %s seconds. This is attempt #%s. The error was %s",
                    $request->getUri()->getPath(),
                    $response->getStatusCode(),
                    number_format($delay, 2),
                    $attemptNumber,
                    $exception->getMessage()
                );
            },
            /* Apparently guzzle can inspect a header request to infer how long to wait between retries...but veracross
             * gives us a unix timestamp. This forces us to use retry_after_date_format ironically because veracross
             * violates the http standards by giving us a unix timestamp
             *
                [x-rate-limit-reset] => Array
                    (
                        [0] => 1760313241
                    )

                [x-rate-limit-remaining] => Array
                    (
                        [0] => 0
                    )

                [x-rate-limit-limit] => Array
                    (
                        [0] => 300
                    )
             */
            'retry_after_header' => 'x-rate-limit-reset',
            'retry_after_date_format' => 'U',
            /* TODO How to implement this?
             */
//            'handler' => $stack,
        ]);
    }


    /**
     * Yield one page of the return payload per iteration,
     * spawning new payloads while Veracross has data for it
     *
     * You'll incur an API request cost when payload size equals page size,
     * and the next request is empty. This is by Veracross design, sadly,
     * and you'll have paid for an empty payload. This is because when the
     * payload == page size, you can't tell if there's more or if that's
     * exactly the amount there was.
     *
     * https://api-docs.veracross.com/docs/docs/ZG9jOjMzNzcxODE-pagination
     *
     *     "If a collection response includes fewer records than the page
     *      size (including zero records) then you have reached the end of
     *      the data. Subsequent requests for page numbers after the end
     *      of the data will continue to be empty"
     *
     * @param string $method
     * @param string $endpoint
     * @param string $scope
     * @param array  $params
     * @param int    $pageSize
     * @return \Generator
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieve(string $method, string $endpoint, string $scope, array $params = [], int $pageSize = IApi::PAGE_SIZE): \Generator
    {
        $pageNum = 1;
        $payload = null;
        $n = 0;

        /* Iterate all pages from Veracross.
         *
         * 1. Check base case, to prevent infinite loopage.
         * 2. If you're requesting your first payload
         * 3. The payload size == page size
         */
        while (($n < self::MAX_PAYLOADS) && (!$payload || \count($payload['data']) === self::PAGE_SIZE)) {
            $this->log(sprintf(
                " >> Retrieving %s @ page %s with %s max elements per page...\n",
                $endpoint,
                $pageNum,
                $pageSize
            ));

            $start = microtime(true);
            $payload = $this->retrieveOne($method, $endpoint, $scope, $pageNum, $pageSize, $params);
            $end = microtime(true);

            $this->log(sprintf(
                "    Obtained %s elements in this payload.\n",
                count($payload['data']),
            ));

            $pageNum++;
            $n++;

            /* Record endpoint usage
             */
            $this->apiUsage->record($endpoint, $end - $start);

            yield $payload;
        }
    }


    /**
     * Retrieve one page of data from the endpoint
     *
     * @param string $method
     * @param string $endpoint
     * @param string $scope
     * @param int    $pageNum
     * @param int    $pageSize
     * @param array  $params
     * @return array
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveOne(string $method, string $endpoint, string $scope, int $pageNum, int $pageSize, array $params = []): array
    {
        $response = null;

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             */
            try {
                $options = [];

                if (!empty($params)) {
                    $options[RequestOptions::QUERY] = $params;

                    /* Debugging:
                     * Find out what guzzle calculated as the endpoint + query string params
                     */
//                    $options['on_stats'] = function (TransferStats $stats) use (&$url) {
//                        $url = $stats->getEffectiveUri();
//                    };
                }

                /* Grab an Access Token
                 */
                $start = microtime(true);
                $accessToken = ($this->accessTokenGenerator->generate($scope))->getToken();
                $end = microtime(true);

                $duration = number_format($end - $start, 2);

                if ($duration > 0) {
                    $this->log(sprintf(
                        " ** Access token generation took %s seconds.\n",
                        $duration,
                    ));
                }

                /* Slam in headers with the dynamic token
                 *
                 * TODO perhaps toggle the X-API-Value-Lists include if you've already
                 *  spawned its contents once, saving us the data transfer...maybe
                 */
                $options['headers'] = [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'X-API-Value-Lists' => 'include',
                    'X-Page-Number' => $pageNum,
                    'X-Page-Size' => $pageSize
                ];

                $start = microtime(true);
                $response = $this->client->request($method, $endpoint, $options);
                $end = microtime(true);

                $this->log(sprintf(
                    " << Client request took %s seconds to obtain response.\n",
                    number_format($end - $start, 2),
                ));

                /* Debugging:
                 * Find out what guzzle calculated as the endpoint + query string params
                 */
//                print_r($url);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                try {
                    $content = $response->getBody()->getContents();

                    if ('' === $content) {
                        return [];
                    }

                    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    /* Couldn't decode json, or server refused something, or something else died
                     * noop; just increase the $fails counter
                     */
                }
            }

            $fails++;
        }

        /* We tried multiple times and failed multiple times
         * This might as well have died
         */
        if ($response) {
            throw new \RuntimeException(
                sprintf(
                    'Bad endpoint %s %s: %s %s after %s retries. Body: %s. Payload: %s',
                    $method,
                    $endpoint,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    $response->getBody()->getContents(),
                    json_encode($params, JSON_THROW_ON_ERROR)
                ),
                $response->getStatusCode(),
                $e ?? null
            );
        }

        /* Couldn't decode json, or server refused something, or something else died
         * Guzzle repeatedly failed too many times and kept throwing exceptions when $response
         * was being requested, so I guess you ended up here
         */
        throw new \DomainException('Could not access server, or bad json payload returned.', 0, $e ?? null);
    }


    /**
     * Experimental
     *
     * @param string $method
     * @param array $endpoints
     * @param string $scope
     * @param array $params
     * @param int $pageSize
     * @return array
     * @throws \Throwable
     */
    public function retrieveConcurrent(string $method, array $endpoints, string $scope, array $params = [], int $pageSize = IApi::PAGE_SIZE): array
    {
        $promises = [];

        foreach ($endpoints as $endpoint) {
            $accessToken = ($this->accessTokenGenerator->generate($scope))->getToken();

            $options = [];

            if (!empty($params)) {
                $options[RequestOptions::QUERY] = $params;
            }

            $options['headers'] = [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'X-API-Value-Lists' => 'include',
                /* Currently hardcoded to be one page for now
                 */
                'X-Page-Number' => 1,
                'X-Page-Size' => $pageSize
            ];

            /* There's this jibberjabber about Guzzle Pools that might be useful at some point
             * but I haven't really understood those yet.
             *
             * GuzzleHttp\Pool
             * https://docs.guzzlephp.org/en/stable/quickstart.html
             */

            $start = microtime(true);
            $promises[] = $this->client->requestAsync($method, $endpoint, $options);
            $end = microtime(true);

            /* Record endpoint usage
             */
            $this->apiUsage->record($endpoint, $end - $start);
        }

        return \GuzzleHttp\Promise\Utils::settle($promises)->wait();
    }


    /**
     * Deeper diagnostics
     *
     * @param string $msg
     * @return void
     */
    private function log(string $msg): void
    {
        if ($this->deepLog) {
            echo $msg;
        }
    }


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }


    /**
     * @return array
     */
    public function getCredentials(): array
    {
        return $this->credentials;
    }
}
