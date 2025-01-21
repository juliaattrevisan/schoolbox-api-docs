<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Request\DataApi;
use LearnositySdk\Request\Init;
use Psr\Log\LoggerInterface;

/**
 * Generates API calls to Learnosity.
 */
class Api
{
    /**
     * From Kit, 25/9/2023
     * Use the Oregon West area for writes, specifically.
     *
     * "Some services, such as Authoring, do not support regions; therefore, their endpoints
     * do not include a region. For Item Authoring endpoints these will point to the Oregon
     * region. All content creation (writes) should go through the Data API - Oregon West
     * endpoint. For reads, you should use the region that your consumer is set up in (for
     * lower latency and hence maximum speed). Note: the region used to read should always
     * match the consumer’s region, not the end user’s region."
     *
     * https://help.learnosity.com/hc/en-us/articles/360000754798-What-Regions-and-Endpoints-Should-I-Use-#us-west-oregon
     *
     * Learnosity API Endpoints - Version must be specified
     */
    private const DATA_API_HOST_WRITE_HUB = 'https://data-or.learnosity.com/v%s/';


    /**
     * Read and Write specifically to the place nearest you
     * TODO Turn into a configuration option one day
     */
    private const DATA_API_HOST_READ = 'https://data-au.learnosity.com/v%s/';
    private const DATA_API_HOST_WRITE = 'https://data-au.learnosity.com/v%s/';


    /**
     * Learnosity has a default 40sec timeout which isn't long enough sometimes?
     */
    private const DATA_API_TIMEOUT = 120;


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
    private const NUM_RETRY = 5;


    /**
     * Jitter litter that gets appended to an exponential backoff
     * to kind of randomise when a request can be retried, to prevent
     * multiple requests inevitably occurring again at the same time
     * to cause another rate limit
     */
    private const JITTER_LOWER = 0;
    private const JITTER_UPPER = 3;

    /**
     * Endpoints for Learnosity API
     */
    private string $dataAPIWriteHostHub;
    private string $dataAPIReadHost;
    private string $dataAPIWriteHost;


    /**
     * Possible job statuses.
     *
     * @see https://reference.learnosity.com/data-api/endpoints/jobs_endpoints
     */
    public const JOB_STATUS_QUEUED    = 'queued';
    public const JOB_STATUS_RUNNING   = 'running';
    public const JOB_STATUS_HALTED    = 'halted';
    public const JOB_STATUS_COMPLETED = 'completed';


    /**
     * Constructor.
     *
     * @param string      $serverName
     * @param string      $learnosityVersion
     * @param string      $consumerKey
     * @param string      $consumerSecret
     * @param Logger|null $logger
     * @throws \DomainException
     */
    public function __construct(
        private string $serverName,
        private string $learnosityVersion,
        #[\SensitiveParameter] private string $consumerKey,
        #[\SensitiveParameter] private string $consumerSecret,
        private ?LoggerInterface $logger = null
    ) {
        /* If not set, I guess loudly announce it
         */
        if ('' === $consumerKey || '' === $consumerSecret) {
            throw new \DomainException('Learnosity is not configured; please check settings');
        }

        /* Write specifically to Oregon, their data hub
         * where replication seems to begin
         */
        $this->dataAPIWriteHostHub = sprintf(self::DATA_API_HOST_WRITE_HUB, $this->learnosityVersion);

        /* Your region's hub
         */
        $this->dataAPIReadHost = sprintf(self::DATA_API_HOST_READ, $this->learnosityVersion);
        $this->dataAPIWriteHost = sprintf(self::DATA_API_HOST_WRITE, $this->learnosityVersion);
    }


    /**
     * This class contains sensitive information: therefore, its debug
     * info should be cleared.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [];
    }


    /**
     * Manufacture a payload to initialise Learnosity javascript app
     *
     * @param string $service
     * @param array  $payload
     * @return string|array
     * @throws \JsonException
     * @throws ValidationException
     */
    public function initOptions(string $service, array $payload): string|array
    {
        /* Optionally log our payloads to learnosity
         */
        $this->logger?->debug(sprintf(
            'Accessed learnosity service "%s" with initialisation payload %s',
            $service,
            json_encode($payload, JSON_THROW_ON_ERROR)
        ));

        return (new Init(
            $service,
            [
                'domain'       => $this->serverName,
                'consumer_key' => $this->consumerKey
            ],
            $this->consumerSecret,
            $payload
        ))->generate();
    }


    /* Hub requests to Oregon specifically
     */

    /**
     * Alias for ActionType "set" endpoints
     * Writs specifically to the Oregon hub
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function dataSetRequestHub(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest($this->dataAPIWriteHostHub, $endpoint, $payload, 'set');
    }


    /* Standard requests to your region's hub
     * This receives replication from Oregon
     */

    /**
     * Alias for ActionType "get" endpoints
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function dataGetRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest($this->dataAPIReadHost, $endpoint, $payload, 'get');
    }


    /**
     * Alias for ActionType "set" endpoints
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function dataSetRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest($this->dataAPIWriteHost, $endpoint, $payload, 'set');
    }


    /**
     * Alias for ActionType "update" endpoints
     *
     * @param string $endpoint
     * @param array $payload
     * @return mixed
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    public function dataUpdateRequest(string $endpoint, array $payload): mixed
    {
        return $this->dataRequest($this->dataAPIWriteHost, $endpoint, $payload, 'update');
    }


    /**
     * Make a specific request to the Data API in Learnosity
     *
     * $actionType corresponds to Action Type in their docs;
     * Learnosity is *not* a RESTful api.
     *
     * @param string $host
     * @param string $endpoint
     * @param array $payload
     * @param string $actionType
     * @return mixed
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     * @throws \Exception
     */
    private function dataRequest(string $host, string $endpoint, array $payload, string $actionType): mixed
    {
        /* Strip slashes, to prevent typos
         */
        $url = $host . trim($endpoint, '/');

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            try {
                $response = (new DataApi([
                    'timeout' => self::DATA_API_TIMEOUT
                ]))->request(
                    $url,
                    [
                        'domain' => $this->serverName,
                        'consumer_key' => $this->consumerKey
                    ],
                    $this->consumerSecret,
                    $payload,
                    $actionType
                );

                $json = $response->json();

                /* Rate Limit exceeded; take a nap for a while before trying again
                 */
                if ($response->getStatusCode() === 429) {
                    throw new RateLimitException();
                }

                /* There was some kind of error and you didn't get a valid json response
                 * The place where the json response would be contains some random text string instead
                 */
                if (null === $json || $response->getStatusCode() < 200 || $response->getStatusCode() > 202) {
                    $responseBody = $response->getBody();

                    /* Sometimes getBody() returns a bool for some reason
                     * We can't really do much with that
                     */
                    if (is_bool($responseBody)) {
                        throw new BooleanException(sprintf(
                            "%s %s: boolean %s returned. Potential curl failure in sdk: %s %s. \n\nHTTP %s %s. We sent this payload: %s",
                            $actionType,
                            $url,
                            (int)$responseBody,
                            $response->getError()['code'],
                            $response->getError()['message'],
                            $response->getStatusCode(),
                            $response->getBody(),
                            json_encode($payload, JSON_THROW_ON_ERROR)
                        ), $response->getStatusCode());
                    }

                    /* Try to decode what learnosity told us
                     */
                    try {
                        $errorPayload = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
                        $message      = $errorPayload['meta']['message'];
                    } catch (\JsonException $e) {
                        // Learnosity gave us something other than JSON?
                        // Set a reasonably sensible message
                        $message = sprintf('non-JSON response: %s', $e->getMessage());
                    }

                    /* Some other unrecoverable error; retrying won't help
                     * Just surface this exception
                     */
                    throw new ApiException(sprintf(
                        "%s %s: %s.\n\nHTTP %s %s. We sent this payload: %s",
                        $actionType,
                        $url,
                        $message,
                        $response->getStatusCode(),
                        $response->getBody(),
                        json_encode($payload, JSON_THROW_ON_ERROR)
                    ), $response->getStatusCode());
                }

                /* No exceptions/rate limits encountered:
                 * leave with the data we requested
                 *
                 * Success!
                 */
                return $json;
            } catch (RateLimitException $e) {
                /* Rate limit reached; retry after napping
                 */
                $jitter = $this->calculateJitter($fails);

                $this->induceNap($fails, $jitter, 'Rate limit reached for Data API on request %s: napping for %s seconds');

                continue;
            } catch (BooleanException $e) {
                /* Random boolean encountered - curl might have died; retry after napping
                 */
                $jitter = $this->calculateJitter($fails);
                $fails++;

                /* Try to diagnose bools further
                 */
                $this->logger?->warning($e->getMessage());

                $this->induceNap($fails, $jitter, 'Attempting retry for potentially transient bool on request %s: napping for %s seconds');

                continue;
            }
        }

        /* Couldn't recover
         */
        throw new ApiException(sprintf(
            '%s %s failed after %s retries. We sent this payload: %s',
            $actionType,
            $url,
            self::NUM_RETRY,
            json_encode($payload, JSON_THROW_ON_ERROR)
        ));
    }


    /**
     * Calculate a random amount of jitter to sprinkle on an exponential backoff
     *
     * @param int $fails
     * @return int
     * @throws \Exception
     */
    private function calculateJitter(int $fails): int
    {
        /* To ensure the first failure's nap is 5 seconds long,
         * as per learnosity's default setting/suggestion
         */
        $jitter = 1;

        /* If we've failed previously, jitter litter some extra seconds
         * to randomise a bit more so we don't all try to start again
         * at the same time (always naps 5sec the first time we fail)
         */
        if ($fails) {
            $jitter = random_int(self::JITTER_LOWER, self::JITTER_UPPER);
        }

        return $jitter;
    }


    /**
     * Take a nap for a period of time
     * "some random jitter" is randomly between 0 and 3 sec by default
     *
     * Sleep for 3+2 seconds
     * then 3+4 seconds (2^2) + some random jitter,
     * then 3+8 seconds (2^3) + some random jitter,
     * then 3+16 seconds (2^4) + some random jitter,
     * then 3+32 seconds (2^5) + some random jitter...
     *
     * ...then give up after multiple full rate limited cycles
     */
    private function induceNap(int $fails, int $jitter, string $logMessage): void
    {
        $napTime = self::NAP_TIMEOUT + pow(2, $fails) + $jitter;

        $this->logger?->debug(sprintf(
            $logMessage,
            $fails,
            $napTime
        ));

        /* Back to sleep Jeffrey
         */
        sleep($napTime);
    }
}
