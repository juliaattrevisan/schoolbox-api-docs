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

class Position extends Base implements ISnapshot
{
    /**
     * @var ParticipantPositionMapper
     */
    private $participantMapper;

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setParticipantMapper(ParticipantPositionMapper::getInstance());
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
}