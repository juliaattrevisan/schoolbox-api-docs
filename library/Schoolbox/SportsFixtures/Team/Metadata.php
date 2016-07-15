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
use \Schoolbox\UserMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
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
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * @var CoachMapper
     */
    protected $coachMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Metadata constructor.
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setPlayers(new Collection());
        $this->setCoaches(new Collection());
        $this->setPositions(new Collection());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setCoachMapper(CoachMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());

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

            /* Collate everything
             */
            $allPlayers = $this->getTeamPlayerMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]);
            $allPositions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId(), ['order' => ['user_id' => 'asc']]);

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

            $coachTags = new CoachTagCloud($this->getCoachMapper(), $this->getUserMapper());

            $allCoaches = $coachTags->findForTeam($team);

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
     * @return TeamPlayerMapper
     */
    private function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    private function setTeamPlayerMapper(TeamPlayerMapper $teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
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

    /**
     * @return CoachMapper
     */
    private function getCoachMapper()
    {
        return $this->coachMapper;
    }

    /**
     * @param CoachMapper $coachMapper
     */
    private function setCoachMapper($coachMapper)
    {
        $this->coachMapper = $coachMapper;
    }

    /**
     * @return UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }
}