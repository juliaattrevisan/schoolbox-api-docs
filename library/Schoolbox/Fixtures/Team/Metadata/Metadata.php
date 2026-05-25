<?php

/**
 * Metadata
 *
 * Strategy Design Pattern
 *
 * Determines whether to show the Current dynamic team composition
 * or to show the snapshot (Historic) composition as saved at Matchup
 * start time; uses Factory to load Strategy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Metadata;

use Schoolbox\Fixtures\Team\Metadata\Strategy\IMetadataStrategy;
use Schoolbox\Collection;
use Schoolbox\Fixtures\Team\Position\Model as TeamPosition;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Player\Model as TeamPlayer;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Team\Position\Participant\Model as TeamPositionParticipant;

class Metadata
{
    /**
     * @var Matchup
     */
    protected $matchup;

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
     * @var IMetadataStrategy
     */
    protected $strategy;

    /**
     * Metadata constructor.
     *
     * @param Matchup $matchup
     * @param IMetadataStrategy $strategy
     */
    public function __construct(Matchup $matchup, IMetadataStrategy $strategy)
    {
        $this->setMatchup($matchup);
        $this->setPlayers(new Collection());
        $this->setCoaches(new Collection());
        $this->setPositions(new Collection());
        $this->setStrategy($strategy);
    }

    /**
     * Collect team composition
     *
     * The players, their positions, and coaches
     */
    public function collect()
    {
        foreach ($this->getMatchup()->getAllTeams() as $team) {
            $this->collectForTeam($team);
        }
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
            /** @var TeamPlayer $currentPlayer */
            foreach ($allPlayers as $currentPlayer) {
                $item = [
                    'user' => $currentPlayer->getUser(),
                    'positions' => new Collection()
                ];

                /* Get all the Positions for this Player
                 */
                /** @var TeamPosition $currentPosition */
                foreach ($allPositions as $currentPosition) {
                    if ($currentPosition instanceof TeamPositionParticipant) {
                        // Historic
                        $playerPosition = $currentPosition;
                    } elseif ($currentPosition instanceof TeamPosition) {
                        // Current
                        $playerPosition = $currentPosition->getPosition();
                    } else {
                        continue;
                    }

                    if ($currentPosition->getUser()->getId() == $currentPlayer->getUser()->getId()) {
                        $item['positions']->add($playerPosition);
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
    private function setPlayers($players)
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
    private function setCoaches($coaches)
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
    private function setPositions($positions)
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
    private function setStrategy($strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * @return Matchup
     */
    public function getMatchup()
    {
        return $this->matchup;
    }

    /**
     * @param Matchup $matchup
     */
    private function setMatchup($matchup)
    {
        $this->matchup = $matchup;
    }
}
