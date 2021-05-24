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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class UnreliableClient extends Client
{

    /**
     * Proportion of time (0.0 to 1.0) that the network fails
     *
     * @var float
     */
    private $failureRate;

    /**
     * @var  Random\IGenerator
     */
    private $generator;


    /**
     * @param float             $failureRate
     * @param Random\IGenerator $generator
     * @param array             $config
     * @throws \InvalidArgumentException If specified failure rate is < 0 or > 1.0
     */
    public function __construct(float $failureRate, Random\IGenerator $generator, array $config = [])
    {
        parent::__construct($config);
        if ($failureRate < 0 || $failureRate > 1.0) {
            throw new \InvalidArgumentException("Failure rate must be between 0 and 1.0 inclusive: {$failureRate}");
        }
        $this->failureRate = $failureRate;
        $this->generator = $generator;
    }


    /**
     * @param RequestInterface $request
     * @param array            $options
     * @return ResponseInterface
     * @throws SimulatedNetworkFailureException At random invocations.
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        // Possibly simulate a network failure
        if (0.0 !== $this->failureRate && $this->generator->generate() <= $this->failureRate) {
            throw new SimulatedNetworkFailureException('Simulated network failure', $request, new \GuzzleHttp\Psr7\Response());
        }
        return parent::send($request, $options);
    }
}
