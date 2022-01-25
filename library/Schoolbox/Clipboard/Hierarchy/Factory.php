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
     * @param string          $host
     * @param string          $token
     */
    public function __construct(
        LoggerInterface $log,
        string $host,
        string $token
    ) {
        $this->log = $log;
        $this->host = $host;
        $this->token = $token;
    }


    /**
     * Produce the individual parts that together, import the fullness
     * of the clipboard Hierarchy
     *
     * @return GenericCollection
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \UnexpectedValueException
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

        /* Various mappers that we need to save bits and pieces inside each strat
         */
        $seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $positionMapper = \Schoolbox\Fixtures\Position\Mapper::getInstance();
        $divisionMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();

        /* Set up composite bus - everybody's data gets on board and shuttled off
         * into schoolbox town
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $this->log,
                $seasonMapper,
                $sportMapper,
                $positionMapper,
                $divisionMapper,
                $teamMapper
            ))
            ->add(new Team(
                $this->log,
                $api,
                $seasonMapper,
                $sportMapper,
                $positionMapper,
                $divisionMapper,
                $teamMapper
            ))
            ->add(new Cleanup(
                $this->log,
                $seasonMapper,
                $sportMapper,
                $positionMapper,
                $divisionMapper,
                $teamMapper
            ));
    }
}
