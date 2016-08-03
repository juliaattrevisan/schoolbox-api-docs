<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Season;

use \Exception;
use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Season\Model as Season;
use \Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;

/**
 * Class Controller
 * @method SeasonMapper getMapper()
 * @package Schoolbox\SportsFixtures\Season
 */
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
        parent::__construct(SeasonMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Seasons
     *
     * @api GET /sportsfixtures/seasons
     * @level 165
     *
     * @param object $context
     */
    public function getList($context = null)
    {

        /* ajax a list of seasons
         */
        if ($this->getRequest()->isXhr()) {
            $query = $this->getRequest()->query->get('query');

            /** @var SeasonMapper $mapper */
            $mapper     = $this->getMapper();
            $allSeasons = $mapper->findByName($query);

            $allSeasons->sortByMultiFields([
                ['start', 'desc'],
                ['end', 'desc'],
            ]);

            $seasons = [];

            /** @var Season $currentSeason */
            foreach ($allSeasons as $currentSeason) {
                $seasons[] = array_combine(['id', 'name'], $currentSeason->findValueLabelPair());
            }

            $this->getResponse()->content->set($this->buildJsonOutput($seasons));
        }
        else {
            try {
                /** @var Collection|Season[] $allSeasons */
                $allSeasons = $this->mapper->findAll(
                    [
                        'order' => [
                            'start' => 'desc',
                            'end' => 'desc',
                        ]
                    ]
                );

                // Get number of division attached to each season to inform
                // user of impact of archive/delete/restore actions.
                $divisionCounts = $this->getMapper()->getDivisionCounts();
                $matchCounts    = $this->getMapper()->getMatchCounts();

                // Ensure all seasons have keys for twig to access
                foreach($allSeasons as $season){
                    if(!isset($divisionCounts[$season->getId()])){
                        $divisionCounts[$season->getId()] = 0;
                    }
                    if(!isset($matchCounts[$season->getId()])){
                        $matchCounts[$season->getId()] = 0;
                    }
                }

                $this->getResponse()->content->set(
                    $this->buildHtmlOutput(
                        '@sportsfixturesSeason/list.twig',
                        [
                            'allSeasons'     => $allSeasons,
                            'divisionCounts' => $divisionCounts,
                            'matchCounts'    => $matchCounts,
                        ]
                    )
                );
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
            }
        }
    }

    /**
     * View a single season
     *
     * @api GET /sportsfixures/seasons/{season.id}
     * @level 165
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSeason/entity.twig', [
            'season' => $entity
        ]));
    }

    /**
     * Display form for create/update season
     *
     * @api GET /sportsfixtures/seasons/new
     * @api GET /sportsfixtures/seasons/{season.id}/edit
     * @level 165
     *
     * @param Model $entity
     * @param object $context
     */
    protected function getForm($entity, $context = null)
    {
        $data = [
            'season' => $entity,
            'action' => $entity->getId() ? 'Modify' : 'Create',
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSeason/create.twig', $data));
    }

    /**
     * Persist method for entity
     *
     * @api POST /sportsfixtures/seasons/
     * @api POST /sportsfixtures/seasons/{season.id}
     * @level 165
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
        try {
            $this->saveSeason(
                $entity,
                $this->getRequest()->post['name'],
                Alaress_Date_Format::newLocalShort($this->getRequest()->post['start']),
                Alaress_Date_Format::newLocalShort($this->getRequest()->post['end'])
            );

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSeason.getList'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
        }
    }

    /**
     * Save Season data to db
     *
     * @param Season $season
     * @param string $name
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     */
    private function saveSeason(Season $season, $name, Alaress_Date_Format $start, Alaress_Date_Format $end)
    {
        $season
            ->setName($name)
            ->setStart($start)
            ->setEnd($end)
            ->setUpdatedAt(Alaress_Date_Format::now());

        $this->mapper->save($season);
    }

    /**
     * Delete the given entity. Deletion is considered to be an idempotent method,
     * so do not return an error on repeat attempts to delete an entity.
     *
     * @param Season $entity
     * @return void
     */
    public function deleteEntity($entity)
    {
        try {
            $this->getMapper()->delete($entity);
            $this->outputPostSuccess($entity);
        } catch (Exception $e) {
            $this->outputPostError($entity, $e);
        }
    }


    /**
     * POST action for archiving a season
     *
     * @param Season $season
     */
    public function archiveEntity(Season $season)
    {
        $season->setArchivedAt(Alaress_Date_Format::now());
        try {
            $this->getMapper()->save($season);
            $this->outputPostSuccess($season);
        } catch (Exception $e) {
            $this->outputPostError($season, $e);
        }
    }

    /**
     * POST action for restoring a previously archived season
     *
     * Deliberately named 'unArchive' (cf. 'restore') to distinguish it from 'unDelete'.
     *
     * @param Season $season
     */
    public function unArchiveEntity(Season $season)
    {
        $season->setArchivedAt(new \Alaress_Date_NullDate());
        try {
            $this->getMapper()->save($season);
            $this->outputPostSuccess($season);
        } catch (Exception $e) {
            $this->outputPostError($season, $e);
        }
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
     * @return static
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * POST action success method.
     *
     * @param Season $season
     */
    protected function outputPostSuccess(Season $season)
    {
        if ($this->getRequest()->isXhr()) {

            $divisionCounts = $this->getMapper()->getDivisionCounts();
            if(!isset($divisionCounts[$season->getId()])){
                $divisionCounts[$season->getId()] = 0;
            }

            $matchCounts = $this->getMapper()->getMatchCounts();
            if(!isset($matchCounts[$season->getId()])){
                $matchCounts[$season->getId()] = 0;
            }

            $this->getResponse()->content->set([
                'template' => "@sportsfixturesSeason/listItem.twig",
                'format'   => 'nochrome',
                'data'     => [
                    'currentSeason'  => $season,
                    'divisionCounts' => $divisionCounts,
                    'matchCounts'    => $matchCounts,
                ]
            ]);
        } else {
            $this->getResponse()->redirect->to($this->getRouter()->generate('sportsfixturesSeason.getList'));
        }
    }

    /**
     * Outputs a POST error, perhaps via AJAX
     *
     * @param Season $season
     * @param Exception $e    The exception which caused the error.
     *
     * @throws Exception If not an AJAX request, pass $e down the chain.
     */
    protected function outputPostError(Season $season, \Exception $e)
    {
        if ($this->getRequest()->isXhr()) {
            // Dump out an AJAX error
            $this->getResponse()->status->setCode(500);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => $e->getMessage(),
            ]);
        } else {
            throw $e;
        }
    }
}
