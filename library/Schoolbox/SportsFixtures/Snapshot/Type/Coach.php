<?php
/**
 * Coach
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot\Type;

use \Schoolbox\SportsFixtures\Snapshot\ISnapshot;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Coach\Model as CurrentCoach;
use \Schoolbox\SportsFixtures\Coach\Participant\Model as ParticipantCoach;
use \Schoolbox\SportsFixtures\Coach\Participant\Mapper as ParticipantCoachMapper;

class Coach extends Base implements ISnapshot
{
    /**
     * @var ParticipantCoachMapper
     */
    private $participantMapper;

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setParticipantMapper(ParticipantCoachMapper::getInstance());
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

                /* Delete all the existing Coaches for the Team
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

                $allCoaches = $this->getMetadata()->getStrategy()->getAllCoaches($currentTeam);

                /** @var CurrentCoach $currentCoach */
                /** @var ParticipantCoach $participantCoach */
                foreach ($allCoaches as $currentCoach) {
                    $participantCoach = $this->getParticipantMapper()->createModel();
                    $participantCoach->setMatch($this->getMatch());
                    $participantCoach->setTeam($currentTeam);
                    $participantCoach->setUser($currentCoach->getUser());
                    $participantCoach->setName($currentCoach->getName());

                    $this->getParticipantMapper()->save($participantCoach);
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return ParticipantCoachMapper
     */
    private function getParticipantMapper()
    {
        return $this->participantMapper;
    }

    /**
     * @param ParticipantCoachMapper $participantMapper
     */
    private function setParticipantMapper($participantMapper)
    {
        $this->participantMapper = $participantMapper;
    }
}