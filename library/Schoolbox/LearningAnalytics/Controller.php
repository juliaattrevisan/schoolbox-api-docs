<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Web\Controller\Base;

use Schoolbox\Learning\Analytics\CohortAnalysis;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\LearningAnalytics\Results\TaskTypes;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\StudentReportCard\ContextSelector;

class Controller extends Base
{

    /**
     * @api   GET /learning/analytics/course/{courseSpecId}
     * @level 165
     *
     * @param array                                  $params
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function course(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'course');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/term/{termSlug}
     * @level 165
     *
     * @param array                                  $params
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function courseWithTerms(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'courseWithTerms');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{taskTypeId}
     * @level 165
     *
     * @param array                                  $params
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function courseWithTask(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'courseWithTask');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{taskTypeId}/term/{termSlug}
     * @level 165
     *
     * @param array                                  $params
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function courseWithTaskAndTerms(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'courseWithTaskAndTerms');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}
     * @level 165
     *
     * @param array                                  $params
     * @param Folder                                 $selectedClassFolder
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function folder(array $params, Folder $selectedClassFolder, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'folder', $selectedClassFolder);
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/term/{termSlug}
     * @level 165
     *
     * @param array                                  $params
     * @param Folder                                 $selectedClassFolder
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function folderWithTerms(array $params, Folder $selectedClassFolder, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'folderWithTerms', $selectedClassFolder);
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}
     * @level 165
     *
     * @param array                                  $params
     * @param Folder                                 $selectedClassFolder
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function folderWithTask(array $params, Folder $selectedClassFolder, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'folderWithTask', $selectedClassFolder);
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}/term/{termSlug}
     * @level 165
     *
     * @param array                                  $params
     * @param Folder                                 $selectedClassFolder
     * @param Specification\Model                    $courseSpecification
     * @param Collection|Implementation\Unit\Model[] $unitImplementations
     * @param Term                                   $term
     */
    public function folderWithTaskAndTerms(array $params, Folder $selectedClassFolder, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'folderWithTaskAndTerms', $selectedClassFolder);
    }


    /**
     * Generate a context selector phplib fragment
     *
     * @param Term $term
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidComponent
     */
    private function generateContextSelector(Term $term)
    {
        /* Url Acrobatics, since the contextselector just appends args continually
         *
         * Trim the leading slash
         * Replace any /term/* args that were previously chosen from contextselector
         * Append /term, since the contextselector term value is something like 2016-1
         */
        $currentUrl = ltrim($this->getRequest()->url->get(PHP_URL_PATH), '/');
        $currentUrl = preg_replace('/\/term\/(.*)/', '', $currentUrl);
        $currentUrl .= '/term';

        /* Construct it in phplib sigh
         */
        $termMapper = new TermMapper();
        $contextSelector = new ContextSelector($currentUrl);
        $contextSelector->setTerms($termMapper->findAll());
        return $contextSelector->renderAsString($this->getDi()->get('sitePage'), ['term' => $term]);
    }


    /**
     * Calculate all data about $allUnitInstances
     *
     * $allUnitInstances will be:
     *
     * + either all the units relevant to the course belonging to the
     * "learningAnalytics/$param['courseId']" route
     *
     * + or all the units relevant to a particular folder in the
     * "learningAnalytics/$param['courseId']/$param['folderId']" route
     *
     * Both are processed identically as data is grouped based on task
     * instances' originating task id, not the individual instance id
     *
     * @param array                                  $params                bag of holding
     * @param Specification\Model                    $courseSpecification   spec from which all data on this page inherits
     * @param Collection|Implementation\Unit\Model[] $unitImplementations   units that match the term filter
     * @param Term                                   $term                  term to filter
     * @param Folder                                 $selectedClassFolder   if given, the class folder to filter
     * @param string                                 $routeUsed             pass directly through to twig for route lookups
     */
    private function process(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term, $routeUsed, Folder $selectedClassFolder = null)
    {
        if ($unitImplementations->count() == 0) {
            throw Error\Mapper::getInstance()->generateException(Error\Mapper::ERROR_NO_ANALYTICS_DATA);
        }

        $analysis = new CohortAnalysis($courseSpecification, $unitImplementations, $selectedClassFolder, $term);


        $metadata = new Metadata($params, $courseSpecification, $unitImplementations, $term, $routeUsed, $selectedClassFolder);

        /* Get a list of all the students involved in each unit
         * and a list of units involved. This gets reused everywhere
         * so just hit the external db once
         */
        $registry = new Registry($unitImplementations);

        /**
         * Results for all the units in each $folder contained in $allFolders
         * (generated by Registry)
         */
        $taskTypes = new TaskTypes($metadata);

        /**
         * Context Selector is a phplib -_-
         */
        $contextSelectorHtml = $this->generateContextSelector($metadata->getTerm());

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@learningAnalytics/course.twig',
            'data'     => [
                'contextSelector' => $contextSelectorHtml,
                'metadata'        => $metadata,
                'overallProgress' => $analysis->getOverallProgress(),
                'results' => [
                    'all' => $analysis->getCohortGradesByActivity($registry),
                    'activities' => [
                        'all'   => $analysis->getActivities($registry, $metadata),
                        'types' => [
                            'all'      => $taskTypes->getActivityTypes(),
                            'selected' => $taskTypes->getSelectedActivityTypeFilter(),
                        ],
                    ],
                    'ranking' => [
                        'top'    => $analysis->getCohortRankings(SORT_DESC),
                        'bottom' => $analysis->getCohortRankings(SORT_ASC),
                    ],
                ],
                'progress' => [
                    'allUnits' => $analysis->getProgressByUnit(),
                ],
                'rubrics' => [
                    'allRubrics' => $analysis->getCohortGradesByIndicator($registry, $metadata),
                ],
                'effort' => [
                    'allEffort' => $analysis->getEffort(),
                ],
            ]
        ]);
    }
}
