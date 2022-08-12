<?php

/**
 * Metadata - Current
 * Strategy Class
 *
 * This handles the accumulation of Players, Positions and Coaches
 * for a Team for the current team/player/position/coach configuration.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Metadata\Strategy;

use Schoolbox\UserMapper;
use Schoolbox\Collection;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Coach\TagCloud as CoachTagCloud;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;

class Current implements IMetadataStrategy
{
    /**
     * @var Matchup
     */
    private $matchup;

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
     * @param Matchup $matchup
     * @param UserMapper $userMapper
     * @param TeamPlayerMapper $teamPlayerMapper
     * @param TeamPositionMapper $teamPositionMapper
     * @param CoachMapper $coachMapper
     */
    public function __construct(Matchup $matchup, UserMapper $userMapper, TeamPlayerMapper $teamPlayerMapper, TeamPositionMapper $teamPositionMapper, CoachMapper $coachMapper)
    {
        $this->setMatchup($matchup);
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
        return $this->getTeamPlayerMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'asc']]);
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
     * @return Collection|\Schoolbox\Fixtures\Coach\Model[]
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
