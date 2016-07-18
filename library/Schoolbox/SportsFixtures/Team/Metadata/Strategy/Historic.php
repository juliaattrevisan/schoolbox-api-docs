<?php
/**
 * HistoricMetadata
 *
 * Gets team composition for a Match that has already occurred
 * Uses cached values instead of the dynamic composition tables
 * sportsfixtures_team_coach, sportsfixtures_team_user and
 * sportsfixtures_team_position - all of which can change at any
 * point in time
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata\Strategy;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\ParticipantMapper as TeamParticipantMapper;
use \Schoolbox\SportsFixtures\Team\Position\ParticipantMapper as TeamPositionParticipantMapper;
use \Schoolbox\SportsFixtures\Coach\ParticipantMapper as TeamCoachPositionParticipantMapper;

class Historic extends Base implements IMetadataStrategy
{
    /**
     * @var TeamParticipantMapper
     */
    protected $teamParticipantMapper;

    /**
     * @var TeamPositionParticipantMapper
     */
    protected $teamPositionParticipantMapper;

    /**
     * @var TeamCoachPositionParticipantMapper
     */
    protected $teamCoachParticipantMapper;

    /**
     * Hook method
     */
    public function setup()
    {
        $this->setTeamParticipantMapper(TeamParticipantMapper::getInstance());
        $this->setTeamPositionParticipantMapper(TeamPositionParticipantMapper::getInstance());
        $this->setTeamCoachParticipantMapper(TeamCoachPositionParticipantMapper::getInstance());
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPlayers(Team $team)
    {
        return $this->getTeamParticipantMapper()->findByColumnList([
            'match_id' => $this->getMatch()->getId(),
            'team_id' => $team->getId()
        ], [
            'order' => [
                'sequence' => 'asc'
            ]
        ]);
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllPositions(Team $team)
    {
        return $this->getTeamPositionParticipantMapper()->findByColumnList([
            'match_id' => $this->getMatch()->getId(),
            'team_id' => $team->getId()
        ], [
            'order' => [
                'user_id' => 'asc'
            ]
        ]);
    }

    /**
     * @inheritdoc
     *
     * @param Team $team
     * @return Collection
     */
    public function getAllCoaches(Team $team)
    {
        return $this->getTeamCoachParticipantMapper()->findByColumnList([
            'match_id' => $this->getMatch()->getId(),
            'team_id' => $team->getId()
        ], [
            'order' => [
                'user_id' => 'asc'
            ]
        ]);
    }

    /* Getters/Setters
     */

    /**
     * @return TeamParticipantMapper
     */
    protected function getTeamParticipantMapper()
    {
        return $this->teamParticipantMapper;
    }

    /**
     * @param TeamParticipantMapper $teamParticipantMapper
     */
    protected function setTeamParticipantMapper($teamParticipantMapper)
    {
        $this->teamParticipantMapper = $teamParticipantMapper;
    }

    /**
     * @return TeamPositionParticipantMapper
     */
    protected function getTeamPositionParticipantMapper()
    {
        return $this->teamPositionParticipantMapper;
    }

    /**
     * @param TeamPositionParticipantMapper $teamPositionParticipantMapper
     */
    protected function setTeamPositionParticipantMapper($teamPositionParticipantMapper)
    {
        $this->teamPositionParticipantMapper = $teamPositionParticipantMapper;
    }

    /**
     * @return TeamCoachPositionParticipantMapper
     */
    protected function getTeamCoachParticipantMapper()
    {
        return $this->teamCoachParticipantMapper;
    }

    /**
     * @param TeamCoachPositionParticipantMapper $teamCoachParticipantMapper
     */
    protected function setTeamCoachParticipantMapper($teamCoachParticipantMapper)
    {
        $this->teamCoachParticipantMapper = $teamCoachParticipantMapper;
    }
}