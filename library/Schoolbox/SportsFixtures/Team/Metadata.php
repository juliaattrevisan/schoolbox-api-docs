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
    private $coaches;

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
        $this->setCoaches(new Collection());
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

            /* Get a list of Players currently in the Team
             */
            $this->getPlayers()->setOffset($team->getId(), new Collection());

            $allPlayers = $this->getTeamUserMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]);

            foreach ($allPlayers as $currentPlayer) {
                $this->getPlayers()->getOffset($team->getId())->add($currentPlayer->getUser());
            }

            /* Get all Positions used in a Team and arrange them
             * for each Player, where applicable
             */
            $this->getPositions()->setOffset($team->getId(), new Collection());

            $allPositions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId(), ['order' => ['user_id' => 'asc']]);

            foreach ($allPositions as $currentPosition) {
                $this->getPositions()->getOffset($team->getId())->setOffset($currentPosition->getUser()->getId(), $currentPosition);
            }

            /* Collect Coaches
             */
            $this->getCoaches()->setOffset($team->getId(), new Collection());

            /* TODO: Extract actual Coaches
             */
            $userMapper = new \Schoolbox\UserMapper();

            $anonymous = new \Schoolbox\User();
            $anonymous->setFirstname('Joe');
            $anonymous->setLastname('Anonymous');

            $allCoaches = [];
            $allCoaches[] = $userMapper->findById(19);
            $allCoaches[] = $anonymous;
            $allCoaches[] = $userMapper->findById(3);

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