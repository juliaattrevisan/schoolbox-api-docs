<?php
/**
 * Player
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Snapshot\Type;

use \Schoolbox\Fixtures\Snapshot\ISnapshot;
use \Schoolbox\Fixtures\Team\Model as Team;
use \Schoolbox\Fixtures\Team\Player\Model as CurrentPlayer;
use \Schoolbox\Fixtures\Team\Player\Participant\Model as ParticipantPlayer;
use \Schoolbox\Fixtures\Team\Player\Participant\Mapper as ParticipantPlayerMapper;
use \Schoolbox\Fixtures\Team\Metadata\Metadata;
use \Schoolbox\Fixtures\Match\Model as Match;

class Player implements ISnapshot
{
    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var ParticipantPlayerMapper
     */
    private $participantMapper;

    /**
     * Player constructor.
     *
     * @param Metadata $metadata
     * @param ParticipantPlayerMapper $participantMapper
     */
    public function __construct(Metadata $metadata, ParticipantPlayerMapper $participantMapper)
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

                /* Delete all the existing Players for the Team
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

                $allPlayers = $this->getMetadata()->getStrategy()->getAllPlayers($currentTeam);

                /** @var CurrentPlayer $currentPlayer */
                /** @var ParticipantPlayer $participantPlayer */
                foreach ($allPlayers as $currentPlayer) {
                    $participantPlayer = $this->getParticipantMapper()->createModel();
                    $participantPlayer->setMatch($match);
                    $participantPlayer->setTeam($currentTeam);
                    $participantPlayer->setUser($currentPlayer->getUser());
                    $participantPlayer->setSequence($currentPlayer->getSequence());

                    $this->getParticipantMapper()->save($participantPlayer);
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return ParticipantPlayerMapper
     */
    private function getParticipantMapper()
    {
        return $this->participantMapper;
    }

    /**
     * @param ParticipantPlayerMapper $participantMapper
     */
    private function setParticipantMapper($participantMapper)
    {
        $this->participantMapper = $participantMapper;
    }

    /**
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param Metadata $metadata
     */
    protected function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }
}