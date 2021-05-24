<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Division\Helper;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;
use Schoolbox\Fixtures\Team\Helper\Translator as TeamTranslator;
use Schoolbox\Fixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\Fixtures\Division\Model as Division;

/**
 * Translates a Division object into a data format that is suitable for use in
 * templates.
 *
 * @package Schoolbox\Fixtures\Division\Metadata
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
     * @var TeamTranslator
     */
    protected $teamTranslator;

    /**
     * Constructor - populates required mappers.
     */
    public function __construct()
    {
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setTeamTranslator(new TeamTranslator());
    }

    /**
     * Builds a template friendly data model from a division.
     *
     * @param Division $division
     * @return array
     */
    public function translateDivision(Division $division)
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
            $dataModel['teams'][] = $this->getTeamTranslator()->translateTeam($team);
        }

        /* A list of assigned ids
         */
        $dataModel['assigned'] = [];

        foreach ($division->getTeams() as $currentTeam) {
            foreach ($currentTeam->getPlayers() as $currentPlayer) {
                $dataModel['assigned'][] = $currentPlayer->getId();
            }
        }

        return $dataModel;
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

    /**
     * @return TeamTranslator
     */
    public function getTeamTranslator()
    {
        return $this->teamTranslator;
    }

    /**
     * @param TeamTranslator $teamTranslator
     * @return static
     */
    public function setTeamTranslator($teamTranslator)
    {
        $this->teamTranslator = $teamTranslator;
        return $this;
    }
}
