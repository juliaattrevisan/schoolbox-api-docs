<?php
/**
 * Metadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\User\Mapper as TeamUserMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;

class Metadata
{
    /**
     * @var Collection
     */
    private $players;

    /**
     * @var Collection
     */
    private $positions;

    /**
     * @var TeamUserMapper
     */
    protected $teamUserMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * Metadata constructor.
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setPlayers(new Collection());
        $this->setPositions(new Collection());
        $this->setTeamUserMapper(TeamUserMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());

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
     * @param Model $team
     */
    private function collectForTeam(Team $team)
    {
        if ($team->getId()) {
            $this->getPlayers()->setOffset($team->getId(), new Collection());

            $allPlayers = $this->getTeamUserMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]);

            foreach ($allPlayers as $currentPlayer) {
                $this->getPlayers()->getOffset($team->getId())->add($currentPlayer->getUser());
            }

            $this->getPositions()->setOffset($team->getId(), new Collection());

            $allPositions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId(), ['order' => ['user_id' => 'asc']]);

            foreach ($allPositions as $currentPosition) {
                $this->getPositions()->getOffset($team->getId())->setOffset($currentPosition->getUser()->getId(), $currentPosition);
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
     * @return TeamUserMapper
     */
    private function getTeamUserMapper()
    {
        return $this->teamUserMapper;
    }

    /**
     * @param TeamUserMapper $teamUserMapper
     * @return static
     */
    private function setTeamUserMapper(TeamUserMapper $teamUserMapper)
    {
        $this->teamUserMapper = $teamUserMapper;
        return $this;
    }

    /**
     * @return TeamPositionMapper
     */
    private function getTeamPositionMapper()
    {
        return $this->teamPositionMapper;
    }

    /**
     * @param TeamPositionMapper $teamPositionMapper
     * @return static
     */
    private function setTeamPositionMapper(TeamPositionMapper $teamPositionMapper)
    {
        $this->teamPositionMapper = $teamPositionMapper;
        return $this;
    }
}