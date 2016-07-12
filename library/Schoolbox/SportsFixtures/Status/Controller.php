<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Status;

use Alaress\Web\Controller\Exception;
use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Result\Model as Result;
use \Schoolbox\SportsFixtures\Status\Model as Status;
use \Schoolbox\SportsFixtures\Status\Mapper as StatusMapper;

class Controller extends AbstractController
{

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(StatusMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * A list of Statuses that can be assigned to Results
     * of a Match
     *
     * @param Status $context
     */
    public function getList($context = null)
    {
        try {
            $allStatus = $this->mapper->findAll(
                [
                    'order' => [
                        'created_at' => 'asc'
                    ]
                ]
            );

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    '@sportsfixturesStatus/list.twig',
                    [
                        'allStatus' => $allStatus
                    ]
                )
            );
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_STATUS_NOT_FOUND);
        }
    }

    /**
     * View a single Status
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
//        try {
//            $sport = $this->getSportMapper()->findById($entity->getSport()->getId());
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
//        }
//
//        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesPosition/entity.twig', [
//            'sport'    => $sport,
//            'position' => $entity
//        ]));
    }

    /**
     * Display form for create/update Status
     *
     * @param Status $entity
     * @param Result $context
     */
    protected function getForm($entity, $context = null)
    {
//        if (empty($context)) {
//            try {
//                $context = $this->getSportMapper()->findById($entity->getSport()->getId());
//            } catch (MapperException $e) {
//                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
//            }
//        }
//
//        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesPosition/create.twig', [
//            'position' => $entity,
//            'sport'    => $context,
//            'action'   => $entity->getId() ? 'Modify' : 'Create'
//        ]));
    }

    /**
     * Persist method for entity
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
//        try {
//            $sportId = $this->getRequest()->post['sportId'];
//            $sport   = $this->getSportMapper()->findById($sportId);
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
//        }
//
//        $this->savePosition($sport, $this->getRequest()->post['name'], $entity);
//
//        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesPosition.getList', [
//            'contextId' => $sportId
//        ]));
    }

    /**
     * Save Status data to db
     *
     * @param string $name
     */
    private function savePosition($name)
    {
//        $position->setName($name);
//        $position->setSport($sport);
//        $position->setUpdatedAt(Alaress_Date_Format::now());
//
//        $this->mapper->save($position);
    }

    /* Getters/Setters
     */

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