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
use Schoolbox\Mapper\Exception\Deleted as DeletedMapperException;
use \Schoolbox\SportsFixtures\Season\Model as Season;
use \Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\Collection;

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
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(SeasonMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
    }

    /**
     * List of Seasons
     *
     * @api GET /sportsfixtures/seasons?query
     * @level 165
     *
     * @param object $context
     */
    public function getList($context = null)
    {
        // in all forms, the retrieved list will be sorted like this
        $options = [
            'order' => [
                'start' => 'desc',
                'end'   => 'desc',
            ]
        ];

        /* ajax a list of seasons
         */
        if (strpos($this->getRequest()->headers->get('accept'), 'application/json') !== false) {

            // quote the query string to protect against injection and the like
            $query = $this->getMapper()->getDatabase()->quoteForLike($this->getRequest()->query->get('query'));

            $search = [
                '~name'       => "%$query%",
                'archived_at' => null
            ];

            // get the list with archived excluded, sort by start and end, then transform to be selectize-compatible
            $seasons = $this->getMapper()->findByColumnList($search, $options)->map(
                function ($season) {
                    /** @var Season $season */
                    return array_combine(['id', 'name'], $season->findValueLabelPair());
                }
            );

            $this->getResponse()->content->set($this->buildJsonOutput($seasons));

            return;
        }

        try {
            /** @var Collection|Season[] $allSeasons */
            $allSeasons = $this->getMapper()->findAll($options);

            // Get number of division attached to each season to inform
            // user of impact of archive/delete/restore actions.
            $divisionCounts = $this->getMapper()->getDivisionCounts();
            $matchCounts    = $this->getMapper()->getMatchCounts();

            // Ensure all seasons have keys for twig to access
            foreach ($allSeasons as $season) {
                if (!isset($divisionCounts[$season->getId()])) {
                    $divisionCounts[$season->getId()] = 0;
                }
                if (!isset($matchCounts[$season->getId()])) {
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
        // Don't permit edit of archived season
        if ($entity->getId() && $entity->isArchived()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
        }

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
     * @api POST sportsfixtures/seasons/{entityId}/delete
     * @level 165
     * @param Season $entity
     * @return void
     */
    public function deleteEntity($entity)
    {
        $post = $this->getRequest()->post;

        // When soft-deleting a season, we must ensure any linked divisions and matches are not left in
        // an invalid state (ie pointing to a soft-deleted season). Hence, if linked entities exist, the caller
        // must specify via POST params how to cascade the delete, along with relevant args.
        // We can either:
        //   * cascade=move&move={seasonId}, or       (move linked divisions/matches to another season)
        //   * cascade=delete                         (delete linked division/matches)

        $seasonId = $entity->getId();

        // Did caller not specify a cascade strategy?
        if (!$post->get('cascade')) {
            // Are there attached divisions or matches ?
            if (
                $this->getMapper()->getDivisionCounts([$seasonId])[$seasonId] ||
                $this->getMapper()->getMatchCounts([$seasonId])[$seasonId]
            ) {
                $message = 'Season has linked divisions and/or matches. Unlink divisions/matches or specify how to cascade the delete';
                $this->outputPostError($entity, new \DomainException($message));
                return;
            }
        }

        // Did caller specify they wished to move the divisions/matches to another season ?
        if ('move' == $post->get('cascade')) {
            // Get that other season
            $otherSeason = $this->getMapper()->findById($post->get('move'));

            // Move any divisions to other season
            $divisions = $this->getDivisionMapper()->findByColumn('season_id', $seasonId);
            foreach ($divisions as $division) {
                $division->setSeason($otherSeason);
            }
            $this->getDivisionMapper()->save($divisions);

            // Move any matches to other season
            $matches = $this->getMatchMapper()->findByColumn('season_id', $seasonId);
            foreach ($matches as $match) {
                $match->setSeason($otherSeason);
            }
            $this->getMatchMapper()->save($matches);
        }

        // Either season now has no linked divisions/matches, or caller specified to cascade the delete
        try {
            $this->getMapper()->delete($entity);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'status' => 200,
            ]));
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


    /**
     * POST action success method.
     *
     * @param Season $season
     */
    protected function outputPostSuccess(Season $season)
    {
        if ($this->getRequest()->isXhr()) {

            $divisionCounts = $this->getMapper()->getDivisionCounts([$season->getId()]);
            if (!isset($divisionCounts[$season->getId()])) {
                $divisionCounts[$season->getId()] = 0;
            }

            $matchCounts = $this->getMapper()->getMatchCounts([$season->getId()]);
            if (!isset($matchCounts[$season->getId()])) {
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
     * @param Exception $e The exception which caused the error.
     *
     * @throws Exception If not an AJAX request, pass $e down the chain.
     */
    protected function outputPostError(Season $season, Exception $e)
    {
        if ($this->getRequest()->isXhr()) {
            // Dump out an AJAX error
            $this->getResponse()->status->setCode(500);
            $this->getResponse()->content->set($this->buildJsonOutput($e->getMessage()));
        } else {
            throw $e;
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
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     * @return static
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
        return $this;
    }

    /**
     * @return MatchMapper
     */
    protected function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper matchMapper
     * @return static
     */
    protected function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
        return $this;
    }

}
