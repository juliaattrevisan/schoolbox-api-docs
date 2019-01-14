<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

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
        $stack = HandlerStack::create();
        $stack->push(
            Middleware::retry(
                function ($retries, RequestInterface $request, ResponseInterface $response) {
                    // Not an error: don't retry
                    if ($response->getStatusCode() < 400) {
                        return false;
                    }

                    // Retry up to 5 times
                    return $retries < 5;
                }
            )
        );

        return new Bridge(
            new HttpClient([
                'handler'  => $stack,
                'base_uri' => 'https://api.rubicon.com/api/',
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
