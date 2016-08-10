<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Team\Helper;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\User;

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
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * Constructor - populates required mappers.
     */
    public function __construct()
    {
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
    }

    /**
     * Builds a template friendly data model from a team.
     *
     * @param Team $team
     * @return array
     */
    public function translateTeam(Team $team)
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

    /**
     * Adds teams and their players into this division.
     *
     * @param Team $team
     * @return User[]
     */
    public function collectPlayers(Team $team)
    {
        $teamPlayers = $this->getTeamPlayerMapper()->findByColumn(
            'team_id',
            $team->getId(),
            ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]
        );

        foreach ($teamPlayers as $teamPlayer) {
            $team->getPlayers()->add($teamPlayer->getUser());
        }

        return $team->getPlayers();
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


    /**
     * @return TeamPlayerMapper
     */
    public function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    public function setTeamPlayerMapper($teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }
}
