<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\ItineraryItem;

use Alaress\Web\Controller\Exception;
use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;


class Controller extends AbstractController
{
    /**
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(ItineraryItemMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of ItineraryItems and the Match they are in
     *
     * @param Match $context
     */
    public function getList($context = null)
    {
        try {
            $allItineraryItems = $this->mapper->findByColumn('match_id', $context->getId(), [
                'order' => [
                    'start_at' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesItineraryItem/list.twig', [
                'match'             => $context,
                'allItineraryItems' => $allItineraryItems
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * View a single position
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
//        $sport = $this->getSportMapper()->findById($entity->getSport()->getId());
//
//        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesPosition/entity.twig', [
//            'sport'    => $sport,
//            'position' => $entity
//        ]));
    }

    /**
     * Display form for create/update position
     *
     * @param Model $entity
     * @param Sport $context
     */
    protected function getForm($entity, $context = null)
    {
//        if (empty($context)) {
//            $context = $this->getSportMapper()->findById($entity->getSport()->getId());
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
//
//            $this->savePosition($sport, $this->getRequest()->post['name'], $entity);
//
//            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesPosition.getList', [
//                'contextId' => $sportId
//            ]));
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(2);
//        }
    }

    /**
     * Save Position data to db
     *
     * @param Sport $sport
     * @param string $name
     * @param Position $position
     */
    private function savePosition($sport, $name, Position $position)
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
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
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