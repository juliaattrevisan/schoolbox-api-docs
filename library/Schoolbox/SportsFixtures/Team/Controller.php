<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team;

use Alaress\Web\Controller\Base;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Model as Division;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;

class Controller extends Base
{

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array  $data
     */
    protected function tpl($template, $data = [])
    {
        $content = [
            'template' => "@sportsfixturesTeam/$template.twig",
            'data'     => $data,
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }

    /**
     * List of Divisions and the Sport they are in
     *
     * @param int $divisionId
     */
    public function getList($divisionId)
    {
        try {
            $division = $this->getDivisionMapper()->findById($divisionId);
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());

            $allTeams = $this->getTeamMapper()->findByColumn('division_id', $division->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->tpl('list', [
                'sport' => $sport,
                'division' => $division,
                'allTeams' => $allTeams
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Team in a particular Division id
     *
     * @param int $divisionId
     */
    public function getCreate($divisionId)
    {
        try {
            $division = $this->getDivisionMapper()->findById($divisionId);

            $this->tpl('create', [
                'division' => $division,
                'action' => 'Create'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Update a Team
     *
     * @param int $teamId
     */
    public function getUpdate($teamId)
    {
        try {
            $team = $this->getTeamMapper()->findById($teamId);
            $division = $this->getDivisionMapper()->findById($team->getDivision()->getId());
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());

            $this->tpl('create', [
                'team' => $team,
                'division' => $division,
                'sport' => $sport,
                'action' => 'Modify'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Team
     *
     * @param Team $team
     */
    public function postCreate(Team $team)
    {
        /* Get around aura's weird regexing
         */
        $this->postModify($team);
    }

    /**
     * Modify a Team
     *
     * @param Team $team
     */
    public function postModify(Team $team)
    {
        try {
            $divisionId = $this->getRequest()->post['divisionId'];
            $division   = $this->getDivisionMapper()->findById($divisionId);

            $this->saveTeam($division, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $team);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesTeam.getList', [
                'divisionId' => $divisionId
            ]));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Delete a Team
     */
    public function postDelete()
    {
        try {
            $team = $this->getTeamMapper()->findById($this->getRequest()->post['teamId']);

            $this->getTeamMapper()->delete($team);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Team data to db
     *
     * @param Division $division
     * @param string $name
     * @param string $url
     * @param Team $team
     */
    private function saveTeam(Division $division, $name, $url, Team $team)
    {
        $team->setName($name);
        $team->setUrl($url);
        $team->setDivision($division);
        $team->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getTeamMapper()->save($team);
    }

    /* Getters/Setters
     */

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
    }

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
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
     */
    public function setTeamMapper($teamMapper)
    {
        $this->teamMapper = $teamMapper;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }
}
