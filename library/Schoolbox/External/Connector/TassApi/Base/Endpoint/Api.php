<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Exception\MissingModule;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer\BrokenPayloadCodec;

/**
 * Class Api
 *
 * Connects to the Tass endpoint, negotiates all the crypto stuff and returns a data
 * payload for the $method you provided. If it fails, it throws a Runtime exception -
 * it can fail when we can't decode the json data, or when we can't establish connectivity
 * etc. but it needs to throw Runtimes because Status and Specification use that.
 */
class Api implements IApi
{

    /**
     * Tass API Version Number
     *
     * Some API products are version-specific, but we default to 3 for now
     */
    public const VERSION_3 = 3;
    public const VERSION_2 = 2;


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes tass endpoints fail for no obvious reason and trying again seems
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
     * The host server
     *
     * @var string
     */
    private string $host;


    /**
     * The module selected
     * It's one of LMS Integration, Student Details, Identity Management
     *
     * @var string
     */
    private string $module;


    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;


    /**
     * @var Codec\Token
     */
    private Codec\Token $tokenCodec;


    /**
     *
     * @var array
     */
    private array $credentials;


    /**
     * @var array
     */
    private array $options;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * Constructor
     *
     * $dsn is the authentication string of the Tass API server
     * This contains strings for companycode, appcode, token
     * These are stored in the Endpoint Key field
     *
     * @param string $host
     * @param string $dsn
     * @param string $module One of the MODULE_* consts defined in DSNCodec
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     */
    public function __construct(string $host, string $dsn, string $module)
    {
        $this->host = $host;

        /* Remember the module that was selected since we need that for diagnosis
         */
        $this->module = $module;

        /* Set up guzzle client
         */
        $this->client = $this->initClient($dsn, $module, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage($module);
    }


    /**
     * Set up guzzle client
     *
     * @param string $dsn
     * @param string $module
     * @param int    $timeout
     * @return Client
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     */
    private function initClient(string $dsn, string $module, int $timeout): Client
    {
        /* Figure out all the credential parts: module, company code, app code, token
         */
        $dsnParts = (new Codec\DSN())->decode($dsn);

        if (!isset($dsnParts['modules'][$module])) {
            throw new MissingModule("Tass API DSN: Invalid module name {$module}");
        }

        /* Remember the module's credentials
         */
        $this->credentials = $dsnParts['modules'][$module];

        /* Remember the optional pieces we offer
         */
        $this->options = $dsnParts['options'] ?? [];

        /* Set up a token codec which does some security stuff based on the
         * params provided to its generateToken() method
         */
        $this->tokenCodec = new Codec\Token($this->credentials['token']);

        /* Set up a reusable guzzle
         */
        return new Client([
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'base_uri' => $this->host,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);
    }


    /**
     * Retrieves endpoint data representing the result from the API call.
     *
     * @param string $method
     * @param array  $params
     * @param int    $version
     * @return array|null
     * @throws DomainException
     * @throws RuntimeException
     * @throws \JsonException
     */
    public function retrieveDocument(string $method, array $params, int $version = self::VERSION_3): ?array
    {

        /* Record endpoint usage
         */
        $this->apiUsage->record($method);

        $queryArgs = [
            'method'  => $method,
            'appcode' => $this->credentials['appcode'],
            'company' => $this->credentials['companycode'],
            'token'   => $this->tokenCodec->generateToken($params, true),
            'v'       => $version,
        ];

        try {
            return $this->tryPayload($method, $params, $queryArgs);
        } catch (\RuntimeException $e) {
            /* That JSON_PRETTY_PRINT thing...it's supposed to be consistent across the board
             *
             * BUT IT'S NOT
             *
             * Some records require JSON_PRETTY_PRINT, like CARR01 at Shalom
             * but some do not, like Eckersley at SJCGT, and will fail if PRETTY_PRINT is on
             *
             * Therefore you need to try both variants before dying entirely
             */
            $queryArgs['token'] = $this->tokenCodec->generateToken($params, false);

            return $this->tryPayload($method, $params, $queryArgs);
        }
    }


    /**
     * Try accessing an endpoint with the $queryArgs provided
     *
     * @param string $method
     * @param array  $params
     * @param array  $queryArgs
     * @return mixed|void
     * @throws DomainException
     * @throws RuntimeException
     * @throws \JsonException
     */
    private function tryPayload(string $method, array $params, array $queryArgs)
    {
        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $lastGuzzleError = '';
        $fails = 0;

        /* Try multiple times in case tass dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             *
             * I don't think tass plays in the same reality as most other people
             * and they might broadcast error states in a successful payload one day
             */
            try {
                $response = $this->client->get($this->host, [
                    'query' => $queryArgs
                ]);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                $lastGuzzleError = $e->getMessage();

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                $contents = json_decode($response->getBody()->getContents(), true);
                // When required parameters are not provided or request is rejected by system setting,
                // this will return __status : invalid and reasons in __invalid
                if (isset($contents['__status']) && $contents['__status'] === 'invalid') {
                    $message = '';
                    if (is_array($contents['__invalid'])) {
                        array_walk($contents['__invalid'], function ($value, $key) use (&$message) {
                            $message .= " $key : $value ";
                        });
                    }
                    throw new RuntimeException(sprintf('Invalid request: %s. Method: %s', $message, $method));
                }
                return $contents;
            }

            /* HTTP 401 Unauthorized is not likely to be a transient issue
             * Skip retrying any other likely nontransient issues by adding
             * their http status codes to this array
             */
            $nonTransient = [401];

            if (in_array($response->getStatusCode(), $nonTransient, true)) {
                $fails = self::NUM_RETRY;
            } else {

                /* We got a response but it's broken
                 * Take a little nap before trying again
                 * (We suspect that it might be timing related, or network traffic)
                 */
                $fails++;

                sleep(self::NAP_TIMEOUT);
            }

            /* We tried multiple times and failed multiple times
             * This might as well have died
             *
             * Exit by throwing exception like we normally would have done
             * otherwise we'll get stuck in an infinite loop and pound the api
             */
            if ($fails >= self::NUM_RETRY) {
                /* Some schools intermittently report a Bad Endpoint in a non-repeating fashion
                 * Sometimes a guy will succeed, sometimes he won't; there's no particular pattern
                 * just by looking at the log itself. In this circumstance, base64 the payload we
                 * tried to use, then pop it into the explorer to see what happened and to replay
                 * the access attempt
                 */
                throw new RuntimeException(sprintf(
                    'Bad endpoint %s: %s %s after %s retries. Payload: %s',
                    $method,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    (new BrokenPayloadCodec())->encode([

                        /* Enables a retry from a shantytown ui
                         */
                        'module' => $this->module,

                        /* These were requested by suds
                         */
                        'method' => $method,
                        'params' => $params,

                        /* This was generated and sent to tass
                         */
                        'queryArgs' => $queryArgs
                    ])
                ));
            }
        }

        /* If you got here, something happened NUM_RETRY times trying to connect
         * and we couldn't even get a payload
         */
        throw new RuntimeException(sprintf(
            'Bad endpoint %s: could not establish connection to server after %s retries. %s',
            $method,
            self::NUM_RETRY,
            $lastGuzzleError
        ));
    }


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }


    /**
     * Get the token we're using in this tass product api.
     * Used for debugging 401 Unauthorized.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->credentials['token'];
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
