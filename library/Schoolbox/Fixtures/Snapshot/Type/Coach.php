<?php
/**
 * Coach
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Snapshot\Type;

use \Schoolbox\Fixtures\Snapshot\ISnapshot;
use \Schoolbox\Fixtures\Team\Model as Team;
use \Schoolbox\Fixtures\Coach\Model as CurrentCoach;
use \Schoolbox\Fixtures\Coach\Participant\Model as ParticipantCoach;
use \Schoolbox\Fixtures\Coach\Participant\Mapper as ParticipantCoachMapper;
use \Schoolbox\Fixtures\Team\Metadata\Metadata;
use \Schoolbox\Fixtures\Match\Model as Match;

class Coach implements ISnapshot
{
    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var ParticipantCoachMapper
     */
    private $participantMapper;

    /**
     * Coach constructor.
     *
     * @param Metadata $metadata
     * @param ParticipantCoachMapper $participantMapper
     */
    public function __construct(Metadata $metadata, ParticipantCoachMapper $participantMapper)
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
        $allTeams = [];

        if ($match->getHomeTeam()) {
            $allTeams[] = $match->getHomeTeam();
        }
        if ($match->getAwayTeam()) {
            $allTeams[] = $match->getAwayTeam();
        }

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
                    'match_id' => $match->getId(),
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
                    $participantCoach->setMatch($match);
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