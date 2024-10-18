<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Terms;

use Alaress\Web\Controller\Base;
use Alaress\Web\Request\Values;
use Alaress_Date_Format;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Term;
use Schoolbox\YearLevel;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Controller extends Base
{
    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @var YearLevel\Mapper
     */
    private $yearLevelMapper;


    /**
     * Constructor
     *
     * @param Term\Mapper $termMapper
     * @param YearLevel\Mapper $yearLevelMapper
     */
    public function __construct(Term\Mapper $termMapper, YearLevel\Mapper $yearLevelMapper)
    {
        $this->termMapper = $termMapper;
        $this->yearLevelMapper = $yearLevelMapper;
    }


    /**
     * GET action for viewing list of terms
     */
    public function getIndex(): void
    {
        /* All available terms, sorted
         */
        $terms = $this->termMapper->findAll(['order' => ['start' => 'desc']]);

        /* Options for prefilling the selectize
         * Indexed by term.id
         */
        $yearLevelValues = [];

        foreach ($terms as $term) {
            foreach ($term->getYearLevels() as $yearLevel) {
                $yearLevelValues[$term->getId()][] = [
                    'id' => $yearLevel->getId(),
                    'name' => $yearLevel->getName()
                ];
            }
        }

        /* Options to choose in the selectize
        */
        $yearLevelOptions = $this->yearLevelMapper->findAll()->map(static function (YearLevel\Model $yearLevel) {
            return [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        })->getAll();

        $this->response = $this->responseFromTemplate(
            '@adminTerms/index.twig',
            [
                'pageCurrent'      => $this->routeGenerator->generate('adminTerms.getIndex'),
                'allBreadcrumbs'   => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminTerms.getIndex')),
                'terms'            => $terms,
                'yearLevelValues'  => $yearLevelValues,
                'yearLevelOptions' => $yearLevelOptions,
            ],
            'Admin - Terms'
        );
    }


    /**
     * POST action for creating a new term
     */
    public function postCreateTerm(): void
    {
        $term = $this->termMapper->createModel();
        try {
            $term = $this->populateModelFromPostData($term, $this->requestPost);
            $this->termMapper->save($term);

            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }


    /**
     * POST action for updating an existing term
     *
     * @param Term\Model $term
     * @throws \Exception
     */
    public function postUpdateTerm(Term\Model $term): void
    {
        try {
            $term = $this->populateModelFromPostData($term, $this->requestPost);
            $this->termMapper->save($term);

            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * Sets a model's fields from post data.
     *
     * @param  Term\Model $term
     * @param  Values     $postdata
     * @return Term\Model
     * @throws \Exception if dates aren't valid
     */
    protected function populateModelFromPostData(Term\Model $term, Values $postdata): Term\Model
    {
        $term->setTitle($postdata->get('title'));
        $term->setCode($postdata->get('code'));

        /* Refresh YearLevel relations
         */
        $yearLevelIds = array_values(array_filter(explode(',', $postdata['yearLevelIds'])));

        $term->setYearLevels($this->yearLevelMapper->findByColumn('id', $yearLevelIds));

        try {
            $term->setStart(Alaress_Date_Format::newLocalShort($postdata->get('start')));
        } catch (\Exception $e) {
            throw new \Exception('Start date is not a valid date.');
        }

        try {
            $term->setDisplayFinish(Alaress_Date_Format::newLocalShort($postdata->get('finish')));

            /* Extraordinary Terms have their actual_finish equal to their display_finish
             */
            if ($term->getExtraordinary()) {
                $term->setActualFinish($term->getDisplayFinish());
            }
        } catch (\Exception $e) {
            throw new \Exception('Finish date is not a valid date.');
        }

        return $term;
    }

    /**
     * POST action for archiving an existing term
     *
     * @param Term\Model $term
     */
    public function postArchiveTerm(Term\Model $term)
    {
        $term->setArchived(true);
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for restoring an existing archived term
     *
     * @param Term\Model $term
     */
    public function postRestoreTerm(Term\Model $term)
    {
        $term->setArchived(false);
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for un-archiving an existing term
     *
     * @param Term\Model $term
     */
    public function postDeleteTerm(Term\Model $term)
    {
        try {
            $this->termMapper->delete($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action success method.
     *
     * @param Term\Model $term
     */
    protected function outputPostSuccess(Term\Model $term)
    {
        if ($this->isXhrRequest()) {
            $this->response = $this->responseFromTemplateUnthemed(
                "@adminTerms/listItem.twig",
                ['term' => $term,]
            );
        } else {
            $this->response = $this->redirectFound($this->routeGenerator->generate('adminTerms.getIndex'));
        }
    }

    /**
     * Outputs a POST error, perhaps via AJAX
     *
     * @param Term\Model $term
     * @param \Exception $e    The exception which caused the error.
     *
     * @throws \Exception If not an AJAX request, pass $e down the chain.
     */
    protected function outputPostError(Term\Model $term, \Exception $e)
    {
        if ($this->isXhrRequest()) {
            // Dump out an AJAX error
            $this->response = $this->responseAsJson($e->getMessage())->withStatus(500);
        } else {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FORM_SUBMIT, $e, $e->getMessage());
        }
    }
}
