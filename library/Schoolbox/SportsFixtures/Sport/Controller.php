<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Sport;

use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Model as Sport;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\Mapper as BaseMapper;

class Controller extends AbstractController
{
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
        parent::__construct(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Sports
     *
     * @param null $context
     */
    public function getList($context = null)
    {
        /* TODO: Not sure if we need this here
         * but I'll set it up anyway just in case
         */
        $allSports = $this->getMapper()->findAll([
            'order' => [
                'name' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/list.twig', [
            'allSports' => $allSports
        ]));
    }

    /**
     * @param Sport $entity
     * @param object $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/create.twig', [
            'sport'  => $entity,
            'action' => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/entity.twig', [
            'sport' => $entity
        ]));
    }

    /**
     * Modify a Sport
     *
     * @param Sport $entity
     */
    public function persistEntity($entity)
    {
        try {
            $this->saveSport($entity, $this->getRequest()->post['name'], $this->getRequest()->post['url']);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSport.getList'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
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

        $this->getMapper()->save($sport);
    }

    /* Getters/Setters
     */

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
