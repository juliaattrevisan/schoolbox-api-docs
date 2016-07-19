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

class Player extends Base implements ISnapshot
{
    /**
     * @var ParticipantPlayerMapper
     */
    private $participantMapper;

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setParticipantMapper(ParticipantPlayerMapper::getInstance());
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
                    'team_id' => $currentTeam->getId()
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
}