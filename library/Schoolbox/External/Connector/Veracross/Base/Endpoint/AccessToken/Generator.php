<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\AccessToken;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\RequestOptions;

/**
 * Class Generator
 *
 * Generate a Veracross Access Token
 *
 * This is required for all endpoint requests and naturally expire after
 * 3600 seconds have elapsed. This lifetime is decided by Veracross, not
 * us, and we can't change its duration.
 *
 * If a token is about to expire, regenerate a new one and return that
 * rather than risk it expiring mid-usage
 */
class Generator
{
    /**
     * Dynamically constructed endpoint
     */
    private const OAUTH_PATH = '%s/oauth/token';


    /**
     * Veracross docs also refer to this as the "token_type"
     * I guess they changed the name in some places, or
     * they are colloquially synonymous
     */
    private const GRANT_TYPE = 'client_credentials';


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
     * Time given to respawn a new token when the existing one is about to expire
     */
    private const TOKEN_OFFSET = 60;


    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 10;


    /**
     * A guzzle client instance
     */
    private ?Client $client = null;


    /**
     * An index of (scope, access token) tuples so this one Generator can be used
     * to spawn and manage multiple scopes, saving you the hassle of making multiple
     * IApi instances that were tied to each scope.
     *
     * @var array
     */
    private array $accessToken = [];


    /**
     * Constructor.
     *
     * @param string $schoolRoute
     * @param string $clientId
     * @param string $clientSecret
     * @param string $authhost
     * @param bool $deepLog
     */
    public function __construct(
        private string $schoolRoute,
        private string $clientId,
        private string $clientSecret,
        private string $authhost,
        private bool $deepLog = false,
    ) {
    }


    /**
     * Generate a token if we need a new one
     *    - when we're creating the first token
     *    - when a previously-created token is about to expire
     *
     * @param string $scope
     * @return AccessToken
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function generate(string $scope): AccessToken
    {
        if (!isset($this->accessToken[$scope])) {
            /* If we have never spawned a token for $scope,
             * then this is our first time using that scope
             * Spawn a new one
             */
            $this->accessToken[$scope] = $this->create($scope);
        } elseif ($this->accessToken[$scope]->isExpired()) {
            /* The token we previously created is about to expire
             * Spawn a new one
             */
            $this->accessToken[$scope] = $this->create($scope);
        }

        /* The one we have is currently alright; reuse it
         */
        return $this->accessToken[$scope];
    }


    /**
     * Create a new AccessToken instance which knows the datetime
     * of its own death so we can easily decide if it's expired
     *
     * Subtract 60 seconds so we have enough time to
     * respawn a new access token.
     *
     * The token only works for the $scope provided.
     *
     * @param string $scope
     * @return AccessToken
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RuntimeException
     */
    private function create(string $scope): AccessToken
    {
        $response = null;

        /* Guzzle the Access Token Generation endpoint
         */
        $endpoint = sprintf(
            self::OAUTH_PATH,
            $this->schoolRoute
        );

        /* Only spawn a client if one doesn't already exist
         */
        if (null === $this->client) {
            $this->client = $this->initClient();
        }

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             */
            try {
                $this->log(sprintf(
                    "Generating access token via %s...\n",
                    $this->authhost
                ));

                $response = $this->client->request('POST', $endpoint, [
                    RequestOptions::FORM_PARAMS => [
                        'grant_type' => self::GRANT_TYPE,
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => $scope
                    ]
                ]);
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

                    /* Unusable content
                     */
                    if ('' === $content) {
                        throw new \DomainException('Access Token returned empty payload during generation.');
                    }

                    /* Get the value of that new token
                     */
                    $token = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

                    /* Predict the death of the token and subtract an arbitrary
                     * amount off its actual lifespan to give us time to spawn another
                     */
                    $expiryTime = time() + (int)$token['expires_in'] - self::TOKEN_OFFSET;

                    /* Spawn the token
                     */
                    return new AccessToken($expiryTime, $token);
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
                    'Bad endpoint %s: %s %s after %s retries, attempting to generate access token. Check credentials and that your Veracross instance allows the scope(s) specified: %s',
                    $endpoint,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    $scope
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
     * Set up guzzle client
     *
     * @return Client
     * @throws InvalidArgumentException
     */
    private function initClient(): Client
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        return new Client([
            'headers' => $headers,
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'base_uri' => $this->authhost,
            'timeout' => self::DEFAULT_TIMEOUT,
            'http_errors' => false,
        ]);
    }


    /**
     * Deeper diagnostics
     *
     * @param string $msg
     * @return void
     */
    private function log(string $msg): void {
        if ($this->deepLog) {
            echo $msg;
        }
    }
}
