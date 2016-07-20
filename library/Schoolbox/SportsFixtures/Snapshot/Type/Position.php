<?php
/**
 * Position
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot\Type;

use \Schoolbox\SportsFixtures\Snapshot\ISnapshot;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Position\Model as CurrentPosition;
use \Schoolbox\SportsFixtures\Team\Position\Participant\Model as ParticipantPosition;
use \Schoolbox\SportsFixtures\Team\Position\Participant\Mapper as ParticipantPositionMapper;
use \Schoolbox\SportsFixtures\Team\Metadata\Metadata;
use \Schoolbox\SportsFixtures\Match\Model as Match;

class Position implements ISnapshot
{
    /**
     * @var Match
     */
    private $match;

    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var ParticipantPositionMapper
     */
    private $participantMapper;

    /**
     * Position constructor.
     *
     * @param Match $match
     * @param Metadata $metadata
     * @param ParticipantPositionMapper $participantMapper
     */
    public function __construct(Match $match, Metadata $metadata, ParticipantPositionMapper $participantMapper)
    {
        $this->setMatch($match);
        $this->setMetadata($metadata);
        $this->setParticipantMapper($participantMapper);
    }

    /**
     * @inheritdoc
     *
     * Only try to snapshot local teams
     * (ignore faceless schools)
     */
    public function snapshot()
    {
        $allTeams = [$this->getMatch()->getHomeTeam(), $this->getMatch()->getAwayTeam()];

        /** @var Team $currentTeam */
        foreach ($allTeams as $currentTeam) {
            if (is_numeric($currentTeam->getId())) {

                /* Delete all the existing Positions for the Team
                 * then re-add them, in case the snapshot already
                 * existed and you need to refresh with a new one
                 *
                 * @param Team $team
                 */
                $this->getParticipantMapper()->deleteByColumnList([
                    'match_id' => $this->getMatch()->getId(),
                    'team_id' => $currentTeam->getId(),

                    /* Bug in deleteByColumnList?
                     */
                    'deleted_at' => null
                ]);

                $allPositions = $this->getMetadata()->getStrategy()->getAllPositions($currentTeam);

                /** @var CurrentPosition $currentPosition */
                /** @var ParticipantPosition $participantPosition */
                foreach ($allPositions as $currentPosition) {
                    $participantPosition = $this->getParticipantMapper()->createModel();
                    $participantPosition->setMatch($this->getMatch());
                    $participantPosition->setTeam($currentTeam);
                    $participantPosition->setUser($currentPosition->getUser());
                    $participantPosition->setName($currentPosition->getPosition()->getName());

                    $this->getParticipantMapper()->save($participantPosition);
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return ParticipantPositionMapper
     */
    private function getParticipantMapper()
    {
        return $this->participantMapper;
    }

    /**
     * @param ParticipantPositionMapper $participantMapper
     */
    private function setParticipantMapper($participantMapper)
    {
        $this->participantMapper = $participantMapper;
    }

    /**
     * @return Match
     */
    private function getMatch()
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

    /**
     * @return Metadata
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param Metadata $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }
}