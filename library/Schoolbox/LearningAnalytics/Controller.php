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

use Aura\Router\Exception\RouteNotFound;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\CohortAnalysis;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\LearningAnalytics\Results\SubmissionTypes;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Application\Exception\Exception as ApplicationException;

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
     * @throws RouteNotFound
     */
    public function course($courseSpecId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = ['courseSpecId' => $courseSpecId, 'termCode' => $currentTerm->getCode()];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithTerms', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/term/{termCode}
     * @level 165
     *
     * @param array                           $params
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function courseWithTerms(array $params, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'courseWithTerms');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{taskTypeId}
     * @level 165
     *
     * @param int $courseSpecId
     * @param int $taskTypeId
     * @throws RouteNotFound
     */
    public function courseWithTask($courseSpecId, $taskTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'taskTypeId'   => $taskTypeId,
            'termCode'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithTaskAndTerms', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{taskTypeId}/term/{termCode}
     * @level 165
     *
     * @param array                           $params
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function courseWithTaskAndTerms(array $params, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($params, $courseSpecification, $unitImplementations, $term, 'courseWithTaskAndTerms');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}
     * @level 165
     *
     * @param int $courseSpecId
     * @param int $folderId
     * @throws RouteNotFound
     */
    public function folder($courseSpecId, $folderId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'folderId'     => $folderId,
            'termCode'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithTerms', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/term/{termCode}
     * @level 165
     *
     * @param array                           $params
     * @param Folder                          $selectedClassFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function folderWithTerms(array $params, Folder $selectedClassFolder, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
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
     * @throws RouteNotFound
     */
    public function folderWithTask($courseSpecId, $folderId, $taskTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId' => $courseSpecId,
            'folderId'     => $folderId,
            'taskTypeId'   => $taskTypeId,
            'termCode'     => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithTaskAndTerms', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}/term/{termCode}
     * @level 165
     *
     * @param array                           $params
     * @param Folder                          $selectedClassFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function folderWithTaskAndTerms(array $params, Folder $selectedClassFolder, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
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
    protected function getTermsWithResults(CourseSpecification $courseSpec, Folder $class = null)
    {
        // Get all courses imported from our courseSpec
        $courseImplMapper = CourseImplementationMapper::getInstance();
        $courseImpls = $courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId(), [], true);
        $courseImplsPerClass = $courseImpls->groupByCallback(function(CourseImplementation $courseImpl){
            return $courseImpl->getFolder()->getId();
        });

        /** @var Term[]|GenericCollection $allTerms */
        $allTerms = $this->termMapper->findAll()->indexBy('id');

        $termsWithResults = $this->filterTermsWithResults($allTerms, $courseImplsPerClass, $class);

        return $termsWithResults->sortByCallback(function(Term $term1, Term $term2){
            return $term1->compare($term2);
        });
    }

    /**
     * @param Term[]|Collection                               $allTerms
     * @param CourseImplementation[][]|Collection[]|Collection $courseImplsPerClass
     * @param Folder|null $class
     * @return Term[]|GenericCollection $terms
     */
    protected function filterTermsWithResults(Collection $allTerms, Collection $courseImplsPerClass, Folder $class = null)
    {
        $terms = $this->termMapper->createCollection();

        foreach($courseImplsPerClass as $classFolderId => $courseImpls){

            // Are we filtering for a specific class ? And it doesn't match this set of courseImports?
            if($class && $class->getId() !== $classFolderId){
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
                                    return $terms;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $terms;
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
     * @param array                           $params                bag of holding
     * @param CourseSpecification             $courseSpecification   spec from which all data on this page inherits
     * @param Collection|UnitImplementation[] $unitImplementations   units that match the term filter
     * @param Term                            $term                  term to filter
     * @param Folder                          $selectedClassFolder   if given, the class folder to filter
     * @param string                          $routeUsed             pass directly through to twig for route lookups
     * @throws ApplicationException
     */
    private function process(array $params, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term, $routeUsed, Folder $selectedClassFolder = null)
    {
        if (0 === $unitImplementations->count()) {
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
        $submissionTypes = new SubmissionTypes($metadata);

        /** @var Term[]|GenericCollection $selectableTerms */
        $selectableTerms = $this->getTermsWithResults($courseSpecification);

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
                            'all'      => $submissionTypes->getSubmissionTypes(),
                            'selected' => $submissionTypes->getSelectedActivityTypeFilter(),
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
