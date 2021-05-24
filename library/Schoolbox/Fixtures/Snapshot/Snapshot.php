<?php

/**
 * Snapshot
 * Composite Design Pattern
 *
 * "Saves the game" for the current configuration of Team Coach,
 * Team Players, Team Positions. This is purely for historical
 * records; all Matches whose start dates are in the past use
 * the historic tables to store coach/player/position data.
 *
 * This class populates the historic tables.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Snapshot;

use Schoolbox\Collection;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Team\Metadata\Factory;
use Schoolbox\Fixtures\Snapshot\Type\Coach as SnapshotCoach;
use Schoolbox\Fixtures\Snapshot\Type\Player as SnapshotPlayer;
use Schoolbox\Fixtures\Snapshot\Type\Position as SnapshotPosition;
use Schoolbox\Fixtures\Coach\Participant\Mapper as ParticipantCoachMapper;
use Schoolbox\Fixtures\Team\Player\Participant\Mapper as ParticipantPlayerMapper;
use Schoolbox\Fixtures\Team\Position\Participant\Mapper as ParticipantPositionMapper;

class Snapshot implements ISnapshot
{

    /**
     * @var Collection
     */
    private $registry;

    /**
     * Snapshot constructor.
     *
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setRegistry(new Collection());

        /* Add registry ammunition here
         *
         * Get the current coach/player/position config (metadata)
         * Load it into snapshot classes and fire away
         */
        $factory = new Factory($match);
        $metadata = $factory->produceCurrent();

        $this->getRegistry()->add(new SnapshotCoach($metadata, ParticipantCoachMapper::getInstance()));
        $this->getRegistry()->add(new SnapshotPlayer($metadata, ParticipantPlayerMapper::getInstance()));
        $this->getRegistry()->add(new SnapshotPosition($metadata, ParticipantPositionMapper::getInstance()));
    }

    /**
     * Execute snapshot for each required piece of a match
     * Coaches, Participants, Positions
     */
    public function snapshot()
    {
        foreach ($this->getRegistry() as $current) {
            $current->snapshot();
        }
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    private function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @param Collection $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }
}
