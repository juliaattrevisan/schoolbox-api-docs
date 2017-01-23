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

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\CohortAnalysis;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\LearningAnalytics\Results\TaskTypes;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;

class Controller extends Base
{
    /** @var TermMapper $termMapper */
    private $termMapper;

    public function __construct(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
    }

    /**
     * @api   GET /learning/analytics/course/{courseSpecId}
     * @level 165
     *
     * @param int $courseSpecId
     */
    public function course($courseSpecId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = ['courseSpecId' => $courseSpecId, 'termSlug' => $currentTerm->getCode()];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithTerms', $data));
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
     * @param int $courseSpecId
     * @param int $taskTypeId
     */
    public function courseWithTask($courseSpecId, $taskTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'taskTypeId'   => $taskTypeId,
            'termSlug'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithTaskAndTerms', $data));
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
     * @param int $courseSpecId
     * @param int $folderId
     */
    public function folder($courseSpecId, $folderId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'folderId'     => $folderId,
            'termSlug'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithTerms', $data));
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
     * @param int $courseSpecId
     * @param int $folderId
     * @param int $taskTypeId
     */
    public function folderWithTask($courseSpecId, $folderId, $taskTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'folderId'     => $folderId,
            'taskTypeId'   => $taskTypeId,
            'termSlug'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithTaskAndTerms', $data));
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
     * Generate a set of terms that have data, so we can insert in navigation context selector
     *
     * Filter by class if supplied.
     *
     * @param CourseSpecification $courseSpec
     * @param Folder              $class
     * @return Term[]|GenericCollection
     */
    private function getTermsHavingData(CourseSpecification $courseSpec, Folder $class = null)
    {
        // Get all courses imported from our courseSpec
        $courseImplMapper = CourseImplementationMapper::getInstance();
        $courseImpls = $courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId(), [], true);
        $courseImplsPerClass = $courseImpls->groupByCallback(function(CourseImplementation $courseImpl){
            return $courseImpl->getFolder()->getId();
        });

        /** @var Term[]|GenericCollection $allTerms */
        $allTerms = $this->termMapper->findAll()->indexBy('id');

        /** @var Term[]|GenericCollection $terms */
        $terms = $this->termMapper->createCollection();

        foreach($courseImplsPerClass as $classFolderId => $courseImpls){

            // Are we filtering for a specific class ? And it doesn't match this set of courseImports?
            if($class && $class->getId() != $classFolderId){
                continue;
            }

            // Examine all courses imported to this folder
            /** @var CourseImplementation[] $courseImpls */
            foreach($courseImpls as $courseImpl){
                // Examine all unitImpls in the courseImpl
                foreach($courseImpl->getUnits() as $unitImpl){
                    // Examine each term that is yet to be confirmed
                    foreach($allTerms as $term){
                        // Does this unit start after the term begins?
                        if($unitImpl->getOpenDate()->compareDateOnly($term->getStart()) >= 0){
                            // Does the unit end before the term finishes?
                            if($term->getActualFinish()->isNull() || $unitImpl->getDueDate()->compareDateOnly($term->getActualFinish()) <= 0 ){
                                // The unit is inside the term.

                                // Add to our list to display
                                $terms->setOffset($term->getId(), $term);

                                // Remove from available so we don't traverse again
                                $allTerms->offsetUnset($term->getId());

                                // Have we selected all terms?
                                if(!$allTerms->count()){
                                    // We're done. Bug out.
                                    break 4;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $terms->sortByCallback(function(Term $term1, Term $term2){
            return $term1->compare($term2);
        });
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

        /** @var Term[]|GenericCollection $selectableTerms */
        $selectableTerms = $this->getTermsHavingData($courseSpecification);

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@learningAnalytics/course.twig',
            'data'     => [
                'terms'           => $selectableTerms,
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
