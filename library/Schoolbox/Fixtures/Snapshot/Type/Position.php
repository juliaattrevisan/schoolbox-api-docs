<?php
/**
 * Position
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Snapshot\Type;

use \Schoolbox\Fixtures\Snapshot\ISnapshot;
use \Schoolbox\Fixtures\Team\Model as Team;
use \Schoolbox\Fixtures\Team\Position\Model as CurrentPosition;
use \Schoolbox\Fixtures\Team\Position\Participant\Model as ParticipantPosition;
use \Schoolbox\Fixtures\Team\Position\Participant\Mapper as ParticipantPositionMapper;
use \Schoolbox\Fixtures\Team\Metadata\Metadata;
use \Schoolbox\Fixtures\Match\Model as Match;

class Position implements ISnapshot
{
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
     * @param Metadata $metadata
     * @param ParticipantPositionMapper $participantMapper
     */
    public function __construct(Metadata $metadata, ParticipantPositionMapper $participantMapper)
    {
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
        /** @var Match $match */
        $match = $this->getMetadata()->getMatch();

        /** @var Team $currentTeam */
        foreach ($match->getAllTeams() as $currentTeam) {
            if (is_numeric($currentTeam->getId())) {

                /* Delete all the existing Positions for the Team
                 * then re-add them, in case the snapshot already
                 * existed and you need to refresh with a new one
                 *
                 * @param Team $team
                 */
                $this->getParticipantMapper()->deleteByColumnList([
                    'match_id' => $match->getId(),
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
                    $participantPosition->setMatch($match);
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