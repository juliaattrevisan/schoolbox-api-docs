<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Http;

use Alaress\Random;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware for simulating network unreliability.
 */
class UnreliableMiddleware
{
    /**
     * Proportion of time (0.0 to 1.0) that the network fails
     */
    private float $failureRate;

    /**
     * RNG
     */
    private Random\IGenerator $generator;

    /**
     * Constructor
     *
     * @param float             $failureRate
     * @param Random\IGenerator $generator
     * @throws InvalidArgumentException If specified failure rate is < 0 or > 1.0
     */
    public function __construct(float $failureRate, Random\IGenerator $generator)
    {
        if ($failureRate < 0 || $failureRate > 1.0) {
            throw new InvalidArgumentException("Failure rate must be between 0 and 1.0 inclusive: {$failureRate}");
        }
        $this->failureRate = $failureRate;
        $this->generator   = $generator;
    }

    /**
     * Handler method: simulate a network failure if the RNG rolls less than the
     * failure rate.
     *
     * @param callable $handler
     * @return callable
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            /** @var Promise $promise */
            $promise = $handler($request, $options);
            if (0.0 !== $this->failureRate && $this->generator->generate() <= $this->failureRate) {
                $promise->reject(
                    new SimulatedNetworkFailureException(
                        'Simulated network failure',
                        $request,
                        new Response(),
                    )
                );
            }

            return $promise;
        };
    }
}
