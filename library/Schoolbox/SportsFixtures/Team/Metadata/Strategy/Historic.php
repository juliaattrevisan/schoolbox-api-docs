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
use \Schoolbox\UserMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\ParticipantMapper as TeamParticipantMapper;
use \Schoolbox\SportsFixtures\Team\Position\ParticipantMapper as TeamPositionParticipantMapper;

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
     * @var Match
     */
    protected $match;

    /**
     * Hook method
     */
    public function setup()
    {
        $this->setTeamParticipantMapper(TeamParticipantMapper::getInstance());
        $this->setTeamPositionParticipantMapper(TeamPositionParticipantMapper::getInstance());
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
        return new Collection();
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
     * @return Match
     */
    protected function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    protected function setMatch($match)
    {
        $this->match = $match;
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
}