<?php
/**
 * Metadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata\Strategy;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;

class Current extends Base implements IMetadataStrategy
{
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
     * Hook method
     */
    public function setup()
    {
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setCoachMapper(CoachMapper::getInstance());
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


}