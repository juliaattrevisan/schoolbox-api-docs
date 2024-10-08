<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\BackChannelLogout;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for back-channel logout requests: abstracts retry logic.
 */
class Client
{
    /**
     * Default timeout (in seconds) between requests.
     */
    private const DEFAULT_TIMEOUT = 2;

    /**
     * Default maximum number of retries.
     */
    private const DEFAULT_MAX_RETRIES = 3;

    /**
     * Constructor
     *
     * @param ClientInterface $httpClient Required for making HTTP requests.
     */
    public function __construct(private ClientInterface $httpClient)
    {
    }

    /**
     * Send the generated request asynchronously: retrying up to `$maxRetries`
     * times, with a timeout of `$timeout` seconds between requests.
     *
     * @param RequestInterface $request
     * @param int              $timeout
     * @param int              $maxRetries
     * @return PromiseInterface
     */
    public function send(RequestInterface $request, int $timeout = self::DEFAULT_TIMEOUT, int $maxRetries = self::DEFAULT_MAX_RETRIES): PromiseInterface
    {
        return $this->httpClient->sendAsync($request, ['handler' => self::buildHandler($timeout, $maxRetries)]);
    }

    /**
     * Build a handler for retrying the request.
     *
     * @param int $timeout
     * @param int $maxRetries
     * @return HandlerStack
     */
    private static function buildHandler(int $timeout, int $maxRetries): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push(
            Middleware::retry(
                static function ($retries, RequestInterface $request, ResponseInterface $response) use ($maxRetries) {
                    // Not an error: don't retry.
                    if ($response->getStatusCode() < 400) {
                        return false;
                    }

                    // Otherwise, retry up to `$maxRetries` times.
                    return $retries < $maxRetries;
                },
                fn () => ($timeout * 1000),
            ),
        );
        return $stack;
    }
}
