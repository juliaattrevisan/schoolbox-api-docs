<?php
/**
 * Player
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot\Type;

use \Schoolbox\SportsFixtures\Snapshot\ISnapshot;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\Model as CurrentPlayer;
use \Schoolbox\SportsFixtures\Team\Player\Participant\Model as ParticipantPlayer;
use \Schoolbox\SportsFixtures\Team\Player\Participant\Mapper as ParticipantPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Metadata\Metadata;
use \Schoolbox\SportsFixtures\Match\Model as Match;

class Player implements ISnapshot
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
     * @var ParticipantPlayerMapper
     */
    private $participantMapper;

    /**
     * Player constructor.
     *
     * @param Match $match
     * @param Metadata $metadata
     * @param ParticipantPlayerMapper $participantMapper
     */
    public function __construct(Match $match, Metadata $metadata, ParticipantPlayerMapper $participantMapper)
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

                /* Delete all the existing Players for the Team
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

                $allPlayers = $this->getMetadata()->getStrategy()->getAllPlayers($currentTeam);

                /** @var CurrentPlayer $currentPlayer */
                /** @var ParticipantPlayer $participantPlayer */
                foreach ($allPlayers as $currentPlayer) {
                    $participantPlayer = $this->getParticipantMapper()->createModel();
                    $participantPlayer->setMatch($this->getMatch());
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