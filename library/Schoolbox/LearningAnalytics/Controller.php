<?php

/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Analytics\CohortAnalysis;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;

class Controller extends Base
{
    /** @var TermMapper $termMapper */
    private $termMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var WorkType\Mapper */
    private $workTypeMapper;


    /**
     * @param TermMapper      $termMapper
     * @param FolderMapper    $folderMapper
     * @param WorkType\Mapper $workTypeMapper
     */
    public function __construct(TermMapper $termMapper, FolderMapper $folderMapper, WorkType\Mapper $workTypeMapper)
    {
        $this->termMapper           = $termMapper;
        $this->folderMapper         = $folderMapper;
        $this->workTypeMapper       = $workTypeMapper;
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}
     * @since 165
     *
     * @param int $courseSpecId
     * @throws RouteNotFound
     */
    public function course($courseSpecId)
    {
        // Redirect to current term
        $currentTerm = $this->termMapper->findForToday();

        $data = ['courseSpecId' => $courseSpecId, 'termCode' => $currentTerm->getCode()];
        $this->response = $this->redirectFound($this->routeGenerator->generate('learningAnalytics.courseWithTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/term/{termCode}
     * @since 165
     *
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param Collection                      $courseImplementationFolders
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function courseWithTerm(
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        Collection $courseImplementationFolders
    ) {
        $this->process(
            $courseSpecification,
            $unitImplsInTerm,
            $dueAssessments,
            $term,
            'course',
            $courseImplementationFolders
        );
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{submissionTypeId}
     * @since 165
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
        $this->response = $this->redirectFound($this->routeGenerator->generate('learningAnalytics.courseWithSubmissionTypeAndTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/task/{submissionTypeId}/term/{termCode}
     * @since 165
     *
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param Collection                      $courseImplementationFolders
     * @param WorkType\Model                  $submissionType
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function courseWithSubmissionTypeAndTerm(
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        Collection $courseImplementationFolders,
        WorkType\Model $submissionType
    ) {
        $this->process(
            $courseSpecification,
            $unitImplsInTerm,
            $dueAssessments,
            $term,
            'course',
            $courseImplementationFolders,
            null,
            $submissionType
        );
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}
     * @since 165
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
        $this->response = $this->redirectFound($this->routeGenerator->generate('learningAnalytics.folderWithTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/term/{termCode}
     * @since 165
     *
     * @param Folder                          $classFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param Collection                      $courseImplementationFolders
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function folderWithTerm(
        Folder $classFolder,
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        Collection $courseImplementationFolders
    ) {
        $this->process(
            $courseSpecification,
            $unitImplsInTerm,
            $dueAssessments,
            $term,
            'folder',
            $courseImplementationFolders,
            $classFolder
        );
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{submissionTypeId}
     * @since 165
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
        $this->response = $this->redirectFound($this->routeGenerator->generate('learningAnalytics.folderWithSubmissionTypeAndTerm', $data));
    }


    /**
     * @api   GET /learning/analytics/course/{courseSpecId}/class/{folderId}/task/{submissionTypeId}/term/{termCode}
     * @since 165
     *
     * @param Folder                          $classFolder
     * @param CourseSpecification             $courseSpecification
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param Collection                      $courseImplementationFolders
     * @param WorkType\Model                  $submissionType
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function folderWithSubmissionTypeAndTerm(
        Folder $classFolder,
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        Collection $courseImplementationFolders,
        WorkType\Model $submissionType
    ) {
        $this->process(
            $courseSpecification,
            $unitImplsInTerm,
            $dueAssessments,
            $term,
            'folder',
            $courseImplementationFolders,
            $classFolder,
            $submissionType
        );
    }


    /**
     * Return a collection of Terms that contains 1 or more assessments that:
     * * are due in that term
     * * are not a Task, and
     * * do not have a mark type of 'none'
     *
     * Filter by class if supplied.
     *
     * @param CourseSpecification $courseSpec
     * @param Folder              $classFolder
     * @return Term[]|GenericCollection
     */
    protected function getNavigableTerms(CourseSpecification $courseSpec, Folder $classFolder = null)
    {
        /** @var GenericCollection|Term[] $allTerms */
        $allTerms       = $this->termMapper->findAll()->indexBy('id');
        $navigableTerms = $this->termMapper->createCollection();

        foreach ($courseSpec->getCourseImplementations() as $courseImpl) {
            // Are we in 'class' (not cohort) context? And courseImpl not for this class ?
            if ($classFolder && $courseImpl->getFolder()->getId() !== $classFolder->getId()) {
                continue;
            }

            foreach ($courseImpl->getUnits() as $unitImpl) {
                foreach ($unitImpl->getAssessables() as $a) {
                    // Is Assessment a Task or has MarkType of 'None' ?
                    if ($a instanceof Task || $a instanceof LessonPlan || MarkType::SLUG_NONE === $a->getMarkType()->getSlug()) {
                        continue;
                    }

                    foreach ($allTerms as $t) {
                        // Is this Assessment due in this Term?
                        if ($t->getStart()->lte($a->getDueAt()) && (!$t->getActualFinish() || $t->getActualFinish()->gt($a->getDueAt()))) {
                            $navigableTerms->setOffset($t->getId(), $t);

                            // Remove from available terms to avoid repeated traversals
                            $allTerms->offsetUnset($t->getId());

                            break;
                        }
                    }
                }
            }
        }

        return $navigableTerms->sortByCallback(function (Term $term1, Term $term2) {
            return $term2->compare($term1);
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
     * @param CourseSpecification             $courseSpecification spec from which all data on this page inherits
     * @param Collection|UnitImplementation[] $unitImplsInTerm     units containing assessments due in current term
     * @param Collection|IAssessable[]        $dueAssessments      assessments due in current Term
     * @param Term                            $term                term to filter
     * @param string                          $routeType           'course'|'folder'
     * @param Collection                      $courseImplementationFolders  A list of folder instances that contain relevant assessments
     * @param Folder|null                     $classFolder         if given, the class folder to filter
     * @param null|WorkType\Model             $submissionType      if specified, a submission type filter
     * @throws MapperException
     * @throws \DebugBar\DebugBarException
     * @throws \DomainException
     * @throws \UnexpectedValueException
     */
    private function process(
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        $routeType,
        Collection $courseImplementationFolders,
        Folder $classFolder = null,
        WorkType\Model $submissionType = null
    ) {
        // All variants of learning analytics reports could take a while
        $this->extendExecutionTime();

        $analysis = new CohortAnalysis($dueAssessments, $classFolder, $submissionType);

        // Ensure we have folder codes
        /** @var FolderCodeMapper $codeMapper */
        $codeMapper = FolderCodeMapper::getInstance();
        $codeMapper->applyCodesToCategories($courseImplementationFolders->filter(static function (Folder $folder) {
            return $folder->getCodes() === null;
        }));

        /** @var Term[]|GenericCollection $navigableTerms */
        $navigableTerms = TermMapper::getInstance()->findAll();
        $termsWithResults = $this->getNavigableTerms($courseSpecification, $classFolder);

        $matchedRoute = $this->router->getMatcher()->getMatchedRoute();
        $currentRoute = $matchedRoute ? $matchedRoute->name : '';
        $data  = [
            'courseSpec'          => $courseSpecification,
            'classFolder'         => $classFolder,
            'submissionType'      => $submissionType,
            'submissionTypes'     => $this->workTypeMapper->findAll(),
            'currentRoute'        => $currentRoute,
            'route'               => $routeType,
            'classFolders'        => $courseImplementationFolders,
            'term'                => $term,
            'terms'               => $navigableTerms,
            'students'            => $analysis->getParticipatingStudents(),
            'overallProgress'     => $analysis->getOverallProgress(),
            'ranking'             => [
                'top'    => $analysis->getCohortRankings(SORT_DESC),
                'bottom' => $analysis->getCohortRankings(SORT_ASC),
            ],
            'dueAssessments'      => $dueAssessments,
            'gradableAssessments' => $analysis->getGradableAssessments(false),
            'weightedAssessments' => $analysis->getWeightedAssessments(),
            'results'             => [
                'assessments' => $analysis->getGradableAssessments(true),
                'grades'      => $analysis->getAssessmentMarks(true),
            ],
            'progress'            => [
                'allUnits' => $analysis->getProgressByUnit(),
            ],
            'rubrics'             =>
                $analysis->getCohortGradesByIndicator($unitImplsInTerm, $term)
            ,
            'effort'              => [
                'allEffort' => $analysis->getEffort(),
            ],
            'termsWithResults'    => $termsWithResults,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAnalytics/course.twig',
            $data,
        );
    }
}
