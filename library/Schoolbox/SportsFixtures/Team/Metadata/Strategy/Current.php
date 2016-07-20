<?php
/**
 * Metadata - Current
 * Strategy Class
 *
 * This handles the accumulation of Players, Positions and Coaches
 * for a Team for the current team/player/position/coach configuration.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata\Strategy;

use \Schoolbox\UserMapper;
use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\SportsFixtures\Match\Model as Match;

class Current implements IMetadataStrategy
{
    /**
     * @var Match
     */
    private $match;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var TeamPlayerMapper
     */
    private $teamPlayerMapper;

    /**
     * @var TeamPositionMapper
     */
    private $teamPositionMapper;

    /**
     * @var CoachMapper
     */
    private $coachMapper;

    /**
     * Current constructor.
     *
     * @param Match $match
     * @param UserMapper $userMapper
     * @param TeamPlayerMapper $teamPlayerMapper
     * @param TeamPositionMapper $teamPositionMapper
     * @param CoachMapper $coachMapper
     */
    public function __construct(Match $match, UserMapper $userMapper, TeamPlayerMapper $teamPlayerMapper, TeamPositionMapper $teamPositionMapper, CoachMapper $coachMapper)
    {
        $this->setMatch($match);
        $this->setUserMapper($userMapper);
        $this->setTeamPlayerMapper($teamPlayerMapper);
        $this->setTeamPositionMapper($teamPositionMapper);
        $this->setCoachMapper($coachMapper);
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPlayers(Team $team)
    {
        return $this->getTeamPlayerMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]);
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPositions(Team $team)
    {
        return $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId(), ['order' => ['user_id' => 'asc']]);
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection|\Schoolbox\SportsFixtures\Coach\Model[]
     */
    public function getAllCoaches(Team $team)
    {
        $coachTags = new CoachTagCloud($this->getCoachMapper(), $this->getUserMapper());

        $allCoaches = $coachTags->findForTeam($team);

        return $allCoaches;
    }

    /* Getters/Setters
     */

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

    /**
     * @return mixed
     */
    private function getMatch()
    {
        return $this->match;
    }

    /**
     * @param mixed $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }
}