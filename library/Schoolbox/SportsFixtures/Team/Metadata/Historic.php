<?php
/**
 * HistoricMetadata
 *
 * Gets team composition for a Match that has already occurred
 * Uses cached values instead of the dynamic composition tables
 * sportsfixtures_team_coach, sportsfixtures_team_user and
 * sportsfixtures_team_position - all of which can change at any
 * point in time
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use \Schoolbox\Collection;
use \Schoolbox\UserMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\ParticipantMapper as TeamParticipantMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;

class Historic extends Base
{
    /**
     * @var TeamParticipantMapper
     */
    protected $teamParticipantMapper;

    /**
     * Historic constructor.
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        /* Set up mappers etc
         */

        parent::__construct($match);
    }

    /**
     * Collect metadata about a Team - its Players and their Positions
     *
     * @param Team $team
     */
    protected function collectForTeam(Team $team)
    {
        if ($team->getId()) {

            /* Get a list of Players currently in the Team
             */
            $this->getPlayers()->setOffset($team->getId(), new Collection());

            /* Collect Coaches
             */
            $this->getCoaches()->setOffset($team->getId(), new Collection());
        }
    }

    /* Getters/Setters
     */

    /**
     * @return TeamParticipantMapper
     */
    protected function getTeamParticipantMapper()
    {
        return $this->teamParticipantMapper;
    }

    /**
     * @param TeamParticipantMapper $teamParticipantMapper
     */
    protected function setTeamParticipantMapper($teamParticipantMapper)
    {
        $this->teamParticipantMapper = $teamParticipantMapper;
    }
}