<?php
/**
 * Metadata
 *
 * Strategy Design Pattern
 *
 * Determines whether to show the Current dynamic team composition
 * or to show the snapshot (Historic) composition as saved at Match
 * start time; uses Factory to load Strategy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use Schoolbox\SportsFixtures\Team\Metadata\Strategy\IMetadataStrategy;
use \Schoolbox\User;
use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Match\Model as Match;

class Metadata
{
    /**
     * @var Collection
     */
    protected $players;

    /**
     * @var Collection
     */
    protected $coaches;

    /**
     * @var Collection
     */
    protected $positions;

    /**
     * @var Strategy\Base
     */
    protected $strategy;

    /**
     * Base constructor.
     *
     * @param Match $match
     */
    public function __construct(Match $match, IMetadataStrategy $strategy)
    {
        $this->setPlayers(new Collection());
        $this->setCoaches(new Collection());
        $this->setPositions(new Collection());
        $this->setStrategy($strategy);

        $this->collect($match);
    }

    /**
     * Collect team composition
     *
     * The players, their positions, and coaches
     *
     * @param Match $match
     */
    protected function collect(Match $match)
    {
        /** @var Team $homeTeam */
        /** @var Team $awayTeam */
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $this->collectForTeam($homeTeam);
        $this->collectForTeam($awayTeam);
    }

    /**
     * Collect metadata about a Team - its Players and their Positions
     *
     * @param Team $team
     */
    protected function collectForTeam(Team $team)
    {
        if ($team->getId()) {

            /* Get a list of Players currently in the Team
             */
            $this->getPlayers()->setOffset($team->getId(), new Collection());

            /* Collate everything
             */
            $allPlayers = $this->getStrategy()->getAllPlayers($team);
            $allPositions = $this->getStrategy()->getAllPositions($team);

            /* Sorry Matt
             */
            foreach ($allPlayers as $currentPlayer) {
                $item = [
                    'user' => $currentPlayer->getUser(),
                    'positions' => new Collection()
                ];

                /* Get all the Positions for this Player
                 */
                foreach ($allPositions as $currentPosition) {
                    if ($currentPosition->getUser()->getId() == $currentPlayer->getUser()->getId()) {
                        $item['positions']->add($currentPosition);
                    }
                }

                /* Stack up!
                 */
                $this->getPlayers()->getOffset($team->getId())->add($item);
            }

            /* Collect Coaches
             */
            $this->getCoaches()->setOffset($team->getId(), new Collection());

            $allCoaches = $this->getStrategy()->getAllCoaches($team);

            foreach ($allCoaches as $currentCoach) {
                $this->getCoaches()->getOffset($team->getId())->add($currentCoach);
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getPlayers()
    {
        return $this->players;
    }

    /**
     * @param Collection $players
     */
    protected function setPlayers($players)
    {
        $this->players = $players;
    }

    /**
     * @return Collection
     */
    public function getCoaches()
    {
        return $this->coaches;
    }

    /**
     * @param Collection $coaches
     */
    protected function setCoaches($coaches)
    {
        $this->coaches = $coaches;
    }

    /**
     * @return Collection
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * @param Collection $positions
     */
    protected function setPositions($positions)
    {
        $this->positions = $positions;
    }

    /**
     * @return mixed
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param mixed $strategy
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
    }
}