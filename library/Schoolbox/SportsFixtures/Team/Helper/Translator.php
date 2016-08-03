<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Team\Helper;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;
use Schoolbox\SportsFixtures\Team\Model as TeamModel;
use Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;

/**
 * Translates a Division object into a data format that is suitable for use in
 * templates.
 *
 * @package Schoolbox\SportsFixtures\Division\Metadata
 */
class Translator
{
    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * Constructor - populates required mappers.
     */
    public function __construct()
    {
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
    }

    /**
     * Builds a template friendly data model from a team.
     *
     * @param TeamModel $team
     * @return array
     */
    public function translateTeam(TeamModel $team)
    {
        $positions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId());

        $playerPosition = [];

        /* Organise positions by player id
         */
        foreach ($positions as $currentPosition) {
            $playerPosition[$currentPosition->getUser()->getId()][] = $currentPosition;
        }

        return [
            'team'      => $team,
            'players'   => $team->getPlayers(),
            'positions' => $playerPosition,
        ];
    }

    /* Getters/setters */

    /**
     * @return TeamPositionMapper
     */
    public function getTeamPositionMapper()
    {
        return $this->teamPositionMapper;
    }

    /**
     * @param TeamPositionMapper $teamPositionMapper
     * @return static
     */
    public function setTeamPositionMapper($teamPositionMapper)
    {
        $this->teamPositionMapper = $teamPositionMapper;
        return $this;
    }
}
