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
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Model as Division;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\ItineraryItem\Model as ItineraryItem;
use \Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;

class Controller extends AbstractController
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
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
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
            /** @var Match $context */
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());

            $allItineraryItems = $this->getMapper()->findByColumn('match_id', $context->getId(), [
                'order' => [
                    'start_at' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesItineraryItem/list.twig', [
                'sport'             => $sport,
                'division'          => $division,
                'match'             => $context,
                'allItineraryItems' => $allItineraryItems
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * View a single Itinerary Item
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
        $match = $this->getMatchMapper()->findById($entity->getMatch()->getId());
        $division = $this->getDivisionMapper()->findById($match->getDivision()->getId());
        $sport = $this->getSportMapper()->findById($division->getSport()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesItineraryItem/entity.twig', [
            'sport'         => $sport,
            'division'      => $division,
            'match'         => $match,
            'itineraryItem' => $entity
        ]));
    }

    /**
     * Display form for create/update Itinerary Items
     *
     * @param Model $entity
     * @param Match $context
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            $context = $this->getMatchMapper()->findById($entity->getMatch()->getId());
        }

        try {
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesItineraryItem/create.twig', [
            'sport'         => $sport,
            'division'      => $division,
            'match'         => $context,
            'itineraryItem' => $entity,
            'action'        => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Persist method for entity
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
        try {
            /** @var Match $match */
            $matchId = $this->getRequest()->post['matchId'];
            $match   = $this->getMatchMapper()->findById($matchId);

            $date = "{$this->getRequest()->post['startAtDate']} {$this->getRequest()->post['startAtTime']}";
            $startAt = \Alaress_Date_Format::newLocal($date);

            $this->saveItineraryItem($match, $this->getRequest()->post['description'], $startAt, $entity);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesItineraryItem.getList', [
                'contextId' => $matchId
            ]));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save ItineraryItem data to db
     *
     * @param Match $match

     * @param string $description
     * @param Alaress_Date_Format $startAt
     * @param Model $itineraryItem
     */
    private function saveItineraryItem(Match $match, $description, \Alaress_Date_Format $startAt, ItineraryItem $itineraryItem)
    {
        $itineraryItem->setMatch($match);
        $itineraryItem->setDescription($description);
        $itineraryItem->setStartAt($startAt);
        $itineraryItem->setUpdatedAt(Alaress_Date_Format::now());

        $this->mapper->save($itineraryItem);
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