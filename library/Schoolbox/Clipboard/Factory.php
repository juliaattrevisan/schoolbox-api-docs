<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Symfony\Component\Lock\LockInterface;

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
     * @var bool
     */
    private bool $perform;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


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
     * @param LockInterface   $lock
     * @param string          $host
     * @param string          $token
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        string $host,
        string $token
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->host = $host;
        $this->token = $token;
    }


    /**
     * Produce the individual parts that together, import the fullness
     * of the clipboard Hierarchy
     *
     * @param bool $membership
     * @param bool $sessions
     * @return GenericCollection
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(bool $membership, bool $sessions): GenericCollection
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
        $teamPlayerMapper = \Schoolbox\Fixtures\Team\Player\Mapper::getInstance();
        $teamPositionMapper = \Schoolbox\Fixtures\Team\Position\Mapper::getInstance();
        $divisionUserMapper = \Schoolbox\Fixtures\Division\User\Mapper::getInstance();
        $userMapper = \Schoolbox\UserMapper::getInstance();

        /* Set up a local cache for (sport, division, team) tuples
         */
        $registry = new Hierarchy\Registry(
            $this->log,
            $this->perform,
            $seasonMapper,
            $sportMapper,
            $positionMapper,
            $divisionMapper,
            $teamMapper
        );

        /* Set up a local cache of (external_id, user_id) tuples
         */
        $userLookup = new \Schoolbox\Clipboard\Hierarchy\Lookup\Person($userMapper);

        /* Set up composite bus - everybody's data gets on board and shuttled off
         * into schoolbox town
         */
        $bus = new GenericCollection(IStrategy::class);

        if ($membership) {
            $bus->add(new Hierarchy\Prepare(
                $this->log,
                $this->perform,
                $this->lock,
                $seasonMapper,
                $sportMapper,
                $positionMapper,
                $divisionMapper,
                $teamMapper,
                $teamPlayerMapper,
                $teamPositionMapper,
                $divisionUserMapper
            ))
            ->add(new Hierarchy\Team(
                $this->log,
                $this->perform,
                $this->lock,
                $api,
                $membership,
                $registry,
                $userLookup,
                $divisionUserMapper,
                $teamPlayerMapper,
                $teamPositionMapper,
                $userMapper
            ))
            ->add(new Hierarchy\Cleanup(
                $this->log,
                $this->perform,
                $this->lock,
                $seasonMapper,
                $sportMapper,
                $positionMapper,
                $divisionMapper,
                $teamMapper
            ));
        }

        /* Optionally load Sessions (the equivalent of schoolbox Matchups)
         * This is where we assign people to positions, add coaches, etc.
         * since that comes out of a separate payload in Clipboard and
         * also gets inserted into schoolbox Fixtures' participations
         *
         * Reuse whatever we might have encountered previously during the
         * Team import - Registry will already know about it
         */
        if ($sessions) {
            $venueMapper = \Schoolbox\Fixtures\Venue\Mapper::getInstance();
            $matchupMapper = \Schoolbox\Fixtures\Matchup\Mapper::getInstance();
            $coachMapper = \Schoolbox\Fixtures\Coach\Mapper::getInstance();
            $teamCoachMapper = \Schoolbox\Fixtures\Team\Coach\Mapper::getInstance();
            $resultMapper = \Schoolbox\Fixtures\Result\Mapper::getInstance();

            $bus->add(new Sessions\Prepare(
                $this->log,
                $this->perform,
                $this->lock,
                $matchupMapper,
                $coachMapper,
                $resultMapper
            ))
            ->add(new Sessions\Sessions(
                $this->log,
                $this->perform,
                $this->lock,
                $api,
                $registry,
                $userLookup,
                $venueMapper,
                $matchupMapper,
                $coachMapper,
                $teamCoachMapper,
                $resultMapper
            ))
            ->add(new Sessions\Cleanup(
                $this->log,
                $this->perform,
                $this->lock,
                $matchupMapper,
                $coachMapper,
                $resultMapper
            ));
        }

        /* The usual stats
         */
        $bus->add(new ApiStatistics(
            $this->log,
            $api
        ));

        return $bus;
    }
}
