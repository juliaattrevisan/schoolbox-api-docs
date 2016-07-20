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
use \Schoolbox\SportsFixtures\Team\Metadata\Metadata;
use \Schoolbox\SportsFixtures\Match\Model as Match;

class Coach implements ISnapshot
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
     * @var ParticipantCoachMapper
     */
    private $participantMapper;

    /**
     * Coach constructor.
     *
     * @param Match $match
     * @param Metadata $metadata
     * @param ParticipantCoachMapper $participantMapper
     */
    public function __construct(Match $match, Metadata $metadata, ParticipantCoachMapper $participantMapper)
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