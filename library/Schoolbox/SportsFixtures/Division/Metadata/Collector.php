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
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\User\Model as DivisionUserModel;
use Schoolbox\SportsFixtures\Division\User\Mapper as DivisionUserMapper;

/**
 * Collects metadata for Schoolbox\SportsFixtures\Division\Model objects,
 * in particular players and teams within players.
 *
 * @package Schoolbox\SportsFixtures\Division\Metadata
 */
class Collector
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var PositionMapper
     */
    protected $positionMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var DivisionUserMapper
     */
    protected $divisionUserMapper;

    /**
     * Constructor - populates required mappers.
     */
    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setDivisionUserMapper(DivisionUserMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
    }

    /**
     * Adds players into this division.
     *
     * @param Division $division
     * @return Division
     */
    public function collectPlayers(Division $division)
    {
        // Load players into division
        // TODO add players into division model as a M2M join
        /** @var DivisionUserModel[] $players */
        $players = $this->getDivisionUserMapper()->findByColumn('division_id', $division->getId(), ['order' => ['sequence' => 'desc']]);
        foreach ($players as $player) {
            $division->getPlayers()->add($player->getUser());
        }
        return $division;
    }

    /**
     * Adds teams and their players into this division.
     *
     * @param Division $division
     * @return Division
     */
    public function collectTeams(Division $division)
    {
        // Load players into teams, and teams into division
        // TODO add players into team model as a M2M join
        try {
            $teams = $this->getTeamMapper()->findByColumn('division_id', $division->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $teamPlayers = $this->getTeamPlayerMapper()->findByColumn(
            'team_id',
            $teams->getColumnValues('id'),
            ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]
        );
        foreach ($teamPlayers as $teamPlayer) {
            $teamPlayerTeamId = $teamPlayer->getTeam()->getId();
            foreach ($teams as $team) {
                $teamId = $team->getId();
                if ($teamPlayerTeamId == $teamId) {
                    $team->getPlayers()->add($teamPlayer->getUser());
                }
            }
        }
        $division->setTeams($teams);
        return $division;
    }

    /* Getters and setters */

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param TeamMapper $teamMapper
     * @return static
     */
    public function setTeamMapper($teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

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

    /**
     * @return DivisionUserMapper
     */
    public function getDivisionUserMapper()
    {
        return $this->divisionUserMapper;
    }

    /**
     * @param DivisionUserMapper $divisionUserMapper
     * @return static
     */
    public function setDivisionUserMapper($divisionUserMapper)
    {
        $this->divisionUserMapper = $divisionUserMapper;
        return $this;
    }
}
