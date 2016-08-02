<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Season;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Season\Model as Season;
use \Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;


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

            $seasons = [];

            /** @var Season $currentSeason */
            foreach ($allSeasons as $currentSeason) {
                $seasons[] = array_combine(['id', 'name'], $currentSeason->findValueLabelPair());
            }

            $this->getResponse()->content->set($this->buildJsonOutput($seasons));
        }
        else {
            try {
                $allSeasons = $this->mapper->findAll(
                    [
                        'order' => [
                            'start' => 'desc',
                            'end' => 'desc',
                        ]
                    ]
                );

                $this->getResponse()->content->set(
                    $this->buildHtmlOutput(
                        '@sportsfixturesSeason/list.twig',
                        [
                            'allSeasons' => $allSeasons
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
        $season->setName($name);
        $season->setStart($start);
        $season->setEnd($end);
        $season->setUpdatedAt(Alaress_Date_Format::now());

        $this->mapper->save($season);
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
