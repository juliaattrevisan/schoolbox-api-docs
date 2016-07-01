<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Result;

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
use \Schoolbox\SportsFixtures\Result\Model as Result;
use \Schoolbox\SportsFixtures\Result\Mapper as ResultMapper;

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
        parent::__construct(ResultMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Results and the Match they are in
     *
     * @param Match $context
     */
    public function getList($context = null)
    {
        try {
            /** @var Match $context */
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        try {
            $allResults = $this->getMapper()->findByColumn('match_id', $context->getId());

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesResult/list.twig', [
                'sport'      => $sport,
                'division'   => $division,
                'match'      => $context,
                'allResults' => $allResults
            ]));
        } catch (MapperException $e) {

            /* No Results for this Match?
             * Prompt to spawn one
             */
            $this->getResponse()->redirect->to($this->getRouter()->generate('sportsfixturesResult.getCreateForm', [
                'contextId' => $context->getId()
            ]));
        }
    }

    /**
     * View a single Result
     *
     * @param Result $entity
     */
    public function getEntity($entity)
    {
        try {
            $match = $this->getMatchMapper()->findById($entity->getMatch()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            $division = $this->getDivisionMapper()->findById($match->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesResult/entity.twig', [
            'sport'    => $sport,
            'division' => $division,
            'match'    => $match,
            'result'   => $entity
        ]));
    }

    /**
     * Display form for create/update Results
     *
     * @param Result $entity
     * @param Match $context
     */
    protected function getForm($entity, $context = null)
    {
//        if (empty($context)) {
//            try {
//                $context = $this->getMatchMapper()->findById($entity->getMatch()->getId());
//            } catch (MapperException $e) {
//                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
//            }
//        }
//
//        try {
//            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
//        }
//
//        try {
//            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
//        }
//
//        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesResult/create.twig', [
//            'sport'    => $sport,
//            'division' => $division,
//            'match'    => $context,
//            'result'   => $entity,
//            'action'   => $entity->getId() ? 'Modify' : 'Create'
//        ]));
    }

    /**
     * Persist method for entity
     *
     * @param Result $entity
     */
    protected function persistEntity($entity)
    {
//        try {
//            /** @var Match $match */
//            $matchId = $this->getRequest()->post['matchId'];
//            $match   = $this->getMatchMapper()->findById($matchId);
//        } catch (MapperException $e) {
//            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
//        }
//
//        $this->saveResult($match, $this->getRequest()->post['description'], $entity);
//
//        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesResult.getList', [
//            'contextId' => $matchId
//        ]));
    }

    /**
     * Save Result data to db
     *
     * @param Match $match

     * @param string $description
     * @param Result $result
     */
    private function saveResult(Match $match, $description, Result $result)
    {
//        $result->setMatch($match);
//        $result->setDescription($description);
//        $result->setUpdatedAt(Alaress_Date_Format::now());
//
//        $this->getMapper()->save($result);
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