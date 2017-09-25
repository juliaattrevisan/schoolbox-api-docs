<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Test;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Learning\InlineGrader\Kami\Exception\SimulatedNetworkFailureException;

class UnreliableClient extends Client
{

    /**
     * Proportion of time (0 to 1.0) that the network fails
     *
     * @var float
     */
    private $failureRate;


    /**
     * @param array $config
     * @param float $failureRate
     */
    public function __construct(array $config = [], float $failureRate)
    {
        parent::__construct($config);
        if ($failureRate < 0 || $failureRate > 1.0) {
            throw new \InvalidArgumentException("Failure rate must be between 0 and 1.0 inclusive: {$failureRate}");
        }
        $this->failureRate = $failureRate;
    }


    /**
     * @param RequestInterface $request
     * @param array            $options
     * @return ResponseInterface
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        // Possibly simulate a network failure
        if (random_int(0, PHP_INT_MAX) <= $this->failureRate * PHP_INT_MAX) {
            throw new SimulatedNetworkFailureException('Simulated network failure', $request);
        }
        return parent::send($request, $options);
    }
}
