<?php

/**
 * Snapshot
 *
 * "Saves the game" for the current configuration of Team Coach,
 * Team Players, Team Positions. This is purely for historical
 * records; all Matches whose start dates are in the past use
 * the historic tables to store coach/player/position data.
 *
 * This class populates the historic tables.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Snapshot;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Match\Model as Match;

class Snapshot
{
    /**
     * @var Match
     */
    private $match;

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
        $this->setMatch($match);
        $this->setRegistry(new Collection());

        /* TODO: Add registry ammunition here
         */
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
     * @return Match
     */
    private function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }

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