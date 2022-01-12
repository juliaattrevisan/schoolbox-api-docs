<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Api;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\GenericCollection;

/**
 * Import the Hierarchy of data from clipboard into schoolbox
 * This consists of Categories, Subcategories and Teams
 */
class Factory
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * Clipboard host url, from the config var
     *
     * @var string
     */
    private string $host;


    /**
     * Clipboard authentication token, from the config var
     *
     * @var string
     */
    private string $token;


    /**
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param string          $host
     * @param string          $token
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $token
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->token = $token;
    }


    /**
     * Produce the individual parts that together, import the fullness
     * of the clipboard Hierarchy
     *
     * @return GenericCollection
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \DomainException
     */
    public function produce(): GenericCollection
    {
        /* Check the required pieces existed
         */
        if ('' === $this->host) {
            throw new \DomainException('Missing Clipboard host url; exiting');
        }

        if ('' === $this->token) {
            throw new \DomainException('Missing Clipboard authentication token; exiting');
        }

        /* Otherwise, carry onwards
         */
        $this->log->info('Accessing api endpoint');

        /* Spawn an IApi instance
         */
        $api = new Api($this->host, $this->token);

        /* Set up composite bus - everybody's xml gets on board and shuttled off
         * into schoolbox town
         */
        $bus = new GenericCollection(IStrategy::class);

        /* TODO: Add strats
         */

        return $bus;
    }
}
