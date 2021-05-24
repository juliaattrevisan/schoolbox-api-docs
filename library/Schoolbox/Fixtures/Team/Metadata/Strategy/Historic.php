<?php

/**
 * Metadata - Historic
 *
 * Gets team composition for a Match that has already occurred
 * Uses cached values instead of the dynamic composition tables
 * sportsfixtures_team_coach, sportsfixtures_team_user and
 * sportsfixtures_team_position - all of which can change at any
 * point in time
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Metadata\Strategy;

use Schoolbox\Collection;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Player\Participant\Mapper as TeamParticipantMapper;
use Schoolbox\Fixtures\Team\Position\Participant\Mapper as TeamPositionParticipantMapper;
use Schoolbox\Fixtures\Coach\Participant\Mapper as TeamCoachParticipantMapper;
use Schoolbox\Fixtures\Match\Model as Match;

class Historic implements IMetadataStrategy
{
    /**
     * @var Match
     */
    private $match;

    /**
     * @var TeamParticipantMapper
     */
    private $teamParticipantMapper;

    /**
     * @var TeamPositionParticipantMapper
     */
    private $teamPositionParticipantMapper;

    /**
     * @var TeamCoachParticipantMapper
     */
    private $teamCoachParticipantMapper;

    /**
     * Historic constructor.
     *
     * @param Match $match
     * @param TeamParticipantMapper $teamParticipantMapper
     * @param TeamPositionParticipantMapper $teamPositionParticipantMapper
     * @param TeamCoachParticipantMapper $teamCoachParticipantMapper
     */
    public function __construct(Match $match, TeamParticipantMapper $teamParticipantMapper, TeamPositionParticipantMapper $teamPositionParticipantMapper, TeamCoachParticipantMapper $teamCoachParticipantMapper)
    {
        $this->setMatch($match);
        $this->setTeamParticipantMapper($teamParticipantMapper);
        $this->setTeamPositionParticipantMapper($teamPositionParticipantMapper);
        $this->setTeamCoachParticipantMapper($teamCoachParticipantMapper);
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
    private function getTeamParticipantMapper()
    {
        return $this->teamParticipantMapper;
    }

    /**
     * @param TeamParticipantMapper $teamParticipantMapper
     */
    private function setTeamParticipantMapper($teamParticipantMapper)
    {
        $this->teamParticipantMapper = $teamParticipantMapper;
    }

    /**
     * @return TeamPositionParticipantMapper
     */
    private function getTeamPositionParticipantMapper()
    {
        return $this->teamPositionParticipantMapper;
    }

    /**
     * @param TeamPositionParticipantMapper $teamPositionParticipantMapper
     */
    private function setTeamPositionParticipantMapper($teamPositionParticipantMapper)
    {
        $this->teamPositionParticipantMapper = $teamPositionParticipantMapper;
    }

    /**
     * @return TeamCoachParticipantMapper
     */
    private function getTeamCoachParticipantMapper()
    {
        return $this->teamCoachParticipantMapper;
    }

    /**
     * @param TeamCoachParticipantMapper $teamCoachParticipantMapper
     */
    private function setTeamCoachParticipantMapper($teamCoachParticipantMapper)
    {
        $this->teamCoachParticipantMapper = $teamCoachParticipantMapper;
    }

    /**
     * @return Match
     */
    public function getMatch()
    {
        return $this->match;
    }

    /**
     * @param Match $match
     */
    private function setMatch($match)
    {
        $this->match = $match;
    }
}
