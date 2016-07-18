<?php
/**
 * Metadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Metadata;

use \Schoolbox\Collection;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Coach\Mapper as CoachMapper;
use \Schoolbox\SportsFixtures\Coach\TagCloud as CoachTagCloud;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;

class Current extends Base
{
    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * @var CoachMapper
     */
    protected $coachMapper;

    /**
     * Current constructor.
     * @param Match $match
     */
    public function __construct(Match $match)
    {
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setCoachMapper(CoachMapper::getInstance());

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

            /* Collate everything
             */
            $allPlayers = $this->getTeamPlayerMapper()->findByColumn('team_id', $team->getId(), ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]);
            $allPositions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId(), ['order' => ['user_id' => 'asc']]);

            /* Sorry Matt
             */
            foreach ($allPlayers as $currentPlayer) {
                $item = [
                    'user' => $currentPlayer->getUser(),
                    'positions' => new Collection()
                ];

                /* Get all the Positions for this Player
                 */
                foreach ($allPositions as $currentPosition) {
                    if ($currentPosition->getUser()->getId() == $currentPlayer->getUser()->getId()) {
                        $item['positions']->add($currentPosition);
                    }
                }

                /* Stack up!
                 */
                $this->getPlayers()->getOffset($team->getId())->add($item);
            }

            /* Collect Coaches
             */
            $this->getCoaches()->setOffset($team->getId(), new Collection());

            $coachTags = new CoachTagCloud($this->getCoachMapper(), $this->getUserMapper());

            $allCoaches = $coachTags->findForTeam($team);

            foreach ($allCoaches as $currentCoach) {
                $this->getCoaches()->getOffset($team->getId())->add($currentCoach);
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return TeamPlayerMapper
     */
    protected function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    protected function setTeamPlayerMapper(TeamPlayerMapper $teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }

    /**
     * @return TeamPositionMapper
     */
    protected function getTeamPositionMapper()
    {
        return $this->teamPositionMapper;
    }

    /**
     * @param TeamPositionMapper $teamPositionMapper
     * @return static
     */
    protected function setTeamPositionMapper(TeamPositionMapper $teamPositionMapper)
    {
        $this->teamPositionMapper = $teamPositionMapper;
        return $this;
    }

    /**
     * @return CoachMapper
     */
    protected function getCoachMapper()
    {
        return $this->coachMapper;
    }

    /**
     * @param CoachMapper $coachMapper
     */
    protected function setCoachMapper($coachMapper)
    {
        $this->coachMapper = $coachMapper;
    }
}