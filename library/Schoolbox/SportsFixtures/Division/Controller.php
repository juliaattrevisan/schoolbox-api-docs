<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use Alaress\Web\Controller\Base;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;

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
            'template' => "@sportsfixturesDivision/$template.twig",
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
     * @param $id
     */
    public function getList($id)
    {
        try {
            $sport = $this->getSportMapper()->findById($id);

            $allDivisions = $this->getDivisionMapper()->findByColumn('sport_id', $sport->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->tpl('list', [
                'sport' => $sport,
                'allDivisions' => $allDivisions
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Division in a particular Sport id
     *
     * @param $id
     */
    public function getCreate($id)
    {
        try {
            $sport = $this->getSportMapper()->findById($id);

            $this->tpl('create', [
                'sport' => $sport,
                'action' => 'Create'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Update a Division
     *
     * @param int $id
     */
    public function getUpdate($id)
    {
        try {
            $division = $this->getDivisionMapper()->findById($id);
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());

            $this->tpl('create', [
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
     * Save a Division
     */
    public function postCreate()
    {
        try {
            $division = $this->getDivisionMapper()->createModel();
            $division->setCreatedAt(\Alaress_Date_Format::now());

            $sportId = $this->getRequest()->post['sportId'];
            $sport = $this->getSportMapper()->findById($sportId);

            $this->saveDivision($sport, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $division);

            $this->getResponse()->redirect->afterPost("/sportsfixtures/division/list/{$sportId}");
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save a Division
     */
    public function postUpdate()
    {
        try {
            /** @var Model $division */
            $division = $this->getDivisionMapper()->findById($this->getRequest()->post['divisionId']);

            $sportId = $this->getRequest()->post['sportId'];
            $sport = $this->getSportMapper()->findById($sportId);

            $this->saveDivision($sport, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $division);

            $this->getResponse()->redirect->afterPost("/sportsfixtures/division/list/{$sportId}");
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Delete a Division
     */
    public function postDelete()
    {
        try {
            /** @var Model $division */
            $division = $this->getDivisionMapper()->findById($this->getRequest()->post['id']);

            $this->getDivisionMapper()->delete($division);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Division data to db
     *
     * @param $sport
     * @param $name
     * @param $url
     * @param Model $division
     */
    private function saveDivision($sport, $name, $url, Model $division)
    {
        $division->setName($name);
        $division->setUrl($url);
        $division->setSport($sport);
        $division->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getDivisionMapper()->save($division);
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
