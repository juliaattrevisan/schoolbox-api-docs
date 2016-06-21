<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Sport;

use Alaress\Web\Controller\Base;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Model as Sport;
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
     * List of Sports
     */
    public function getList()
    {
        /* TODO: Not sure if we need this here
         * but I'll set it up anyway just in case
         */
        $allSports = $this->getSportMapper()->findAll([
            'order' => [
                'name' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/list.twig', [
            'allSports' => $allSports
        ]));
    }

    /**
     * Create a Sport
     */
    public function getCreate()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/create.twig', [
            'action' => 'Create'
        ]));
    }

    /**
     * Update a Sport
     *
     * @param int $sportId
     */
    public function getUpdate($sportId)
    {
        try {
            $sport = $this->getSportMapper()->findById($sportId);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/create.twig', [
                'sport' => $sport,
                'action' => 'Modify'
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Sport
     *
     * @param Sport $sport
     */
    public function postCreate(Sport $sport)
    {
        $this->postModify($sport);
    }

    /**
     * Modify a Sport
     *
     * @param Sport $sport
     */
    public function postModify(Sport $sport)
    {
        try {
            $this->saveSport($sport, $this->getRequest()->post['name'], $this->getRequest()->post['url']);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSport.getList'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Delete a Sport
     */
    public function postDelete()
    {
        try {
            /** @var Sport $sport */
            $sport = $this->getSportMapper()->findById($this->getRequest()->post['sportId']);
            $this->getSportMapper()->delete($sport);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Sport data to db
     *
     * @param Sport $sport
     * @param string $name
     * @param string $url
     */
    private function saveSport(Sport $sport, $name, $url)
    {
        $sport->setName($name);
        $sport->setUrl($url);
        $sport->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getSportMapper()->save($sport);
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
