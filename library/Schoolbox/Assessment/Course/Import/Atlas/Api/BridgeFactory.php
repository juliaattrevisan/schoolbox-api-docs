<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Predis\ClientInterface as RedisClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Factory class for the bridge between Atlas calls and their implementation.
 */
class BridgeFactory
{
    /**
     * Max number of retries for a request to the Atlas API.
     */
    private const MAX_RETRIES = 5;


    /**
     * Base timeout (in milliseconds) for the jitter algorithm.
     */
    private const BASE_TIMEOUT = 50;


    /**
     * @var string
     */
    private $token;


    /**
     * @var RedisClient
     */
    private $redisClient;


    /**
     * Constructor
     *
     * @param string      $token
     * @param RedisClient $redisClient
     */
    public function __construct(string $token, RedisClient $redisClient)
    {
        $this->token       = $token;
        $this->redisClient = $redisClient;
    }


    /**
     * Build the bridge class.
     *
     * @return Bridge
     */
    public function build(): Bridge
    {
        // Need one extra retry to be able to generate a delay for attempts = MAX_RETRIES
        $jitter = new Jitter(new FloatGenerator(), self::MAX_RETRIES + 1, self::BASE_TIMEOUT);
        $stack  = HandlerStack::create();
        $stack->push(
            Middleware::retry(
                function ($retries, RequestInterface $request, ResponseInterface $response) {
                    // Not an error: don't retry
                    if ($response->getStatusCode() < 400) {
                        return false;
                    }

                    // Retry up to MAX_RETRIES times
                    return $retries < self::MAX_RETRIES;
                },
                [$jitter, 'calculateJitter']
            )
        );

        return new Bridge(
            new HttpClient([
                'handler'  => $stack,
                'base_uri' => 'https://api.onatlas.com/api/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept'        => 'application/json',
                ],
            ]),
            $this->redisClient,
            (new JsonDecoderFactory())->build()
        );
    }
}
