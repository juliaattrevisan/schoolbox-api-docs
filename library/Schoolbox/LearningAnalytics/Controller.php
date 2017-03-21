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
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/term/{termCode}
     * @level 165
     *
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function courseWithTerm(CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($courseSpecification, $unitImplementations, $term, 'course');
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{submissionTypeId}
     * @level 165
     *
     * @param int $courseSpecId
     * @param int $submissionTypeId
     * @throws RouteNotFound
     */
    public function courseWithSubmissionType($courseSpecId, $submissionTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId'     => $courseSpecId,
            'submissionTypeId' => $submissionTypeId,
            'termCode'         => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.courseWithSubmissionTypeAndTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{submissionTypeId}/term/{termCode}
     * @level 165
     *
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @param int                             $submissionTypeId
     * @throws ApplicationException
     */
    public function courseWithSubmissionTypeAndTerm(CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term, $submissionTypeId)
    {
        $this->process($courseSpecification, $unitImplementations, $term, null, $submissionTypeId);
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
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/term/{termCode}
     * @level 165
     *
     * @param Folder                          $classFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @throws ApplicationException
     */
    public function folderWithTerm(Folder $classFolder, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term)
    {
        $this->process($courseSpecification, $unitImplementations, $term, 'folder', $classFolder);
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{submissionTypeId}
     * @level 165
     *
     * @param int $courseSpecId
     * @param int $folderId
     * @param int $submissionTypeId
     * @throws RouteNotFound
     */
    public function folderWithSubmissionType($courseSpecId, $folderId, $submissionTypeId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = [
            'courseSpecId'     => $courseSpecId,
            'folderId'         => $folderId,
            'submissionTypeId' => $submissionTypeId,
            'termCode'         => $currentTerm->getCode(),
        ];
        $this->getResponse()->redirect->to($this->getRouter()->generate('learningAnalytics.folderWithSubmissionTypeAndTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{submissionTypeId}/term/{termCode}
     * @level 165
     *
     * @param Folder                          $classFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term                            $term
     * @param int                             $submissionTypeId
     * @throws ApplicationException
     */
    public function folderWithSubmissionTypeAndTerm(Folder $classFolder, CourseSpecification $courseSpecification, Collection $unitImplementations, Term $term, $submissionTypeId)
    {
        $this->process($courseSpecification, $unitImplementations, $term, 'folder', $classFolder, $submissionTypeId);
    }


    /**
     * Generate a set of terms that have data, so we can insert in navigation context selector
     *
     * Filter by class if supplied.
     *
     * @param CourseSpecification $courseSpec
     * @param Folder              $class
     * @return Term[]|GenericCollection
     * @todo - needs to return a list of terms that contain assessments that are due in that term
     */
    protected function getSelectableTerms(CourseSpecification $courseSpec, Folder $class = null)
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
     * @param CourseSpecification             $courseSpecification   spec from which all data on this page inherits
     * @param Collection|UnitImplementation[] $unitImplementations   units that match the term filter
     * @param Term                            $term                  term to filter
     * @param Folder                          $selectedClassFolder   if given, the class folder to filter
     * @param string                          $routeType             'course'|'folder'
     * @param null|int                        $submissionTypeId      if specified, a submission type filter
     * @throws ApplicationException
     */
    private function process(
        CourseSpecification $courseSpecification,
        Collection $unitImplementations,
        Term $term,
        $routeType,
        Folder $selectedClassFolder = null,
        $submissionTypeId = null
)
    {
        $analysis = new CohortAnalysis($courseSpecification, $unitImplementations, $selectedClassFolder, $term);
        $metadata = new Metadata($unitImplementations, $selectedClassFolder);

        /* Get a list of all the students involved in each unit
         * and a list of units involved. This gets reused everywhere
         * so just hit the external db once
         */
        $registry = new Registry($unitImplementations);

        /**
         * Results for all the units in each $folder contained in $allFolders
         * (generated by Registry)
         */
        $submissionTypes = new SubmissionTypes($submissionTypeId);

        /** @var Term[]|GenericCollection $selectableTerms */
        $selectableTerms = $this->getSelectableTerms($courseSpecification);

        $data = [
            'courseSpec'       => $courseSpecification,
            'classFolder'      => $selectedClassFolder,
            'submissionType'   => $submissionTypes->getSelectedSubmissionType(),
            'submissionTypes'  => $submissionTypes->getSubmissionTypes(),
            'currentRoute'     => $this->getRouter()->getMatchedRoute()->name,
            'route'            => $routeType,
            'folders'          => $metadata->getUnitImplementationFolders(),

            'term'             => $term,
            'terms'            => $selectableTerms,


            'overallProgress' => $analysis->getOverallProgress(),
            'results' => [
                'ranking' => [
                    'top'    => $analysis->getCohortRankings(SORT_DESC),
                    'bottom' => $analysis->getCohortRankings(SORT_ASC),
                ],
                'all' => $analysis->getCohortGradesByActivity($unitImplementations),
                'activities' => [
                    'all'   => $analysis->getActivities($unitImplementations, $term),
                    'types' => [
                        'all'      => $submissionTypes->getSubmissionTypes(),
                        'selected' => $submissionTypes->getSelectedSubmissionType(),
                    ],
                ],

            ],
            'progress' => [
                'allUnits' => $analysis->getProgressByUnit(),
            ],
            'rubrics' => [
                'allRubrics' => $analysis->getCohortGradesByIndicator($unitImplementations, $registry->getStudents()),
            ],
            'effort' => [
                'allEffort' => $analysis->getEffort(),
            ],
        ];

        $this->getResponse()->content->set([
            'template' => '@learningAnalytics/course.twig',
            'data'     => $data,
        ]);
    }
}
