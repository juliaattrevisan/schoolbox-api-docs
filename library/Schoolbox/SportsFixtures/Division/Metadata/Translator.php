<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Division\Metadata;

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Team\Position\Model as TeamPositionModel;
use Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\User\Model as DivisionUserModel;
use Schoolbox\SportsFixtures\Division\User\Mapper as DivisionUserMapper;

/**
 * Translates a Division object into a data format that is suitable for use in
 * templates.
 *
 * @package Schoolbox\SportsFixtures\Division\Metadata
 */
class Translator
{
    /**
     * @var PositionMapper
     */
    protected $positionMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * Constructor - populates required mappers.
     */
    public function __construct()
    {
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
    }

    /**
     * Builds a template friendly data model from a division.
     *
     * @param Division $division
     * @return array
     */
    public function buildDivisionTemplateDataModel(Division $division)
    {
        $dataModel = [
            'division'  => $division,
            'players'   => [],
            'teams'     => [],
            'positions' => [],
        ];
        foreach ($division->getPlayers() as $player) {
            $playerDataModel = [
                'player' => $player,
                'teams'  => [],
            ];
            foreach ($division->getTeams() as $team) {
                if ($team->isPlayerInTeam($player)) {
                    $playerDataModel['teams'][] = $team;
                }
            }
            $dataModel['players'][] = $playerDataModel;
        }
        foreach ($division->getTeams() as $team) {
            $dataModel['teams'][] = $this->buildTeamTemplateDataModel($team);
        }

        $dataModel['positions'] = $this->getPositionMapper()->findByColumn('sport_id', $division->getSport()->getId());
        return $dataModel;
    }

    /**
     * Builds a template friendly data model from a team.
     *
     * @param Team $team
     * @return array
     */
    public function buildTeamTemplateDataModel(Team $team)
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
     * @return PositionMapper
     */
    public function getPositionMapper()
    {
        return $this->positionMapper;
    }

    /**
     * @param PositionMapper $positionMapper
     * @return static
     */
    public function setPositionMapper($positionMapper)
    {
        $this->positionMapper = $positionMapper;
        return $this;
    }

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
