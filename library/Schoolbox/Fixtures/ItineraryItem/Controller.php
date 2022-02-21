<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\ItineraryItem;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\Fixtures\ItineraryItem\Mapper as ItineraryItemMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Fixtures\ItineraryItem
 */
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
     * @var MatchupMapper
     */
    protected $matchupMapper;


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
        $this->setMatchupMapper(MatchupMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }


    /**
     * List of ItineraryItems and the Match they are in
     *
     * @param Matchup $context
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getList($context = null)
    {
        try {
            /** @var Matchup $context */
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

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


    /**
     * View a single Itinerary Item
     *
     * @param Model $entity
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getEntity($entity)
    {
        try {
            $matchup = $this->getMatchupMapper()->findById($entity->getMatchup()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            $division = $this->getDivisionMapper()->findById($matchup->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesItineraryItem/entity.twig', [
            'sport'         => $sport,
            'division'      => $division,
            'match'         => $matchup,
            'itineraryItem' => $entity
        ]));
    }


    /**
     * Display form for create/update Itinerary Items
     *
     * @param Model $entity
     * @param Matchup $context
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Schoolbox\Application\Exception\Exception
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            try {
                $context = $this->getMatchupMapper()->findById($entity->getMatchup()->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
            }
        }

        try {
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
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
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    protected function persistEntity($entity)
    {
        try {
            $matchId = $this->getRequest()->post['matchId'];
            $matchup = $this->getMatchupMapper()->findById($matchId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        if (strlen($this->getRequest()->post['startAtDate'])) {
            $date    = $this->getRequest()->post['startAtDate'];
            $startAt = \Alaress_Date_Format::newLocal($date);
        } else {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $this->saveItineraryItem($matchup, $this->getRequest()->post['description'], $startAt, $entity);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesMatch.getModifyForm', [
            'entityId' => $matchId
        ]));
    }


    /**
     * Save ItineraryItem data to db
     *
     * @param Matchup             $matchup
     * @param string              $description
     * @param Alaress_Date_Format $startAt
     * @param Model               $itineraryItem
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveItineraryItem(Matchup $matchup, $description, \Alaress_Date_Format $startAt, ItineraryItem $itineraryItem)
    {
        $itineraryItem->setMatchup($matchup);
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
     * @return MatchupMapper
     */
    public function getMatchupMapper()
    {
        return $this->matchupMapper;
    }

    /**
     * @param MatchupMapper $matchupMapper
     */
    public function setMatchupMapper($matchupMapper)
    {
        $this->matchupMapper = $matchupMapper;
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
