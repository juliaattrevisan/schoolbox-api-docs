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
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
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
use Schoolbox\Assessment\Component\SubmissionBox\WorkType as SubmissionType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper as SubmissionTypeMapper;

class Controller extends Base
{

    /** @var TermMapper $termMapper */
    private $termMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var SubmissionTypeMapper */
    private $submissionTypeMapper;


    /**
     * @param TermMapper           $termMapper
     * @param FolderMapper         $folderMapper
     * @param SubmissionTypeMapper $submissionTypeMapper
     */
    public function __construct(TermMapper $termMapper, FolderMapper $folderMapper, SubmissionTypeMapper $submissionTypeMapper)
    {
        $this->termMapper           = $termMapper;
        $this->folderMapper         = $folderMapper;
        $this->submissionTypeMapper = $submissionTypeMapper;
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
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function courseWithTerm(CourseSpecification $courseSpecification, Collection $unitImplsInTerm, Collection $dueAssessments, Term $term)
    {
        $this->process($courseSpecification, $unitImplsInTerm, $dueAssessments, $term, 'course');
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
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param SubmissionType                  $submissionType
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function courseWithSubmissionTypeAndTerm(
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        SubmissionType $submissionType
    ) {
        $this->process($courseSpecification, $unitImplsInTerm, $dueAssessments, $term, 'course', null, $submissionType);
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
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    public function folderWithTerm(
        Folder $classFolder,
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term
    ) {
        $this->process($courseSpecification, $unitImplsInTerm, $dueAssessments, $term, 'folder', $classFolder);
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
     * @param Collection|UnitImplementation[] $unitImplsInTerm
     * @param Collection|IAssessable[]        $dueAssessments
     * @param Term                            $term
     * @param SubmissionType                  $submissionType
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
        SubmissionType $submissionType
    ) {
        $this->process($courseSpecification, $unitImplsInTerm, $dueAssessments, $term, 'folder', $classFolder, $submissionType);
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
                    if ($a instanceof Task || MarkType::SLUG_NONE === $a->getMarkType()->getSlug()) {
                        continue;
                    }

                    foreach ($allTerms as $t) {
                        // Is this Assessment due in this Term?
                        if ($t->getStart()->lte($a->getDueAt()) && ($t->getActualFinish()->isNull() || $t->getActualFinish()->gt($a->getDueAt()))) {
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
     * @param CourseSpecification             $courseSpecification spec from which all data on this page inherits
     * @param Collection|UnitImplementation[] $unitImplsInTerm     units containing assessments due in current term
     * @param Collection|IAssessable[]        $dueAssessments      assessments due in current Term
     * @param Term                            $term                term to filter
     * @param Folder                          $ClassFolder         if given, the class folder to filter
     * @param string                          $routeType           'course'|'folder'
     * @param null|SubmissionType             $submissionType      if specified, a submission type filter
     * @throws ApplicationException
     * @throws \DomainException
     * @throws MapperException
     */
    private function process(
        CourseSpecification $courseSpecification,
        Collection $unitImplsInTerm,
        Collection $dueAssessments,
        Term $term,
        $routeType,
        Folder $ClassFolder = null,
        SubmissionType $submissionType = null
    ) {
        $analysis = new CohortAnalysis($dueAssessments, $ClassFolder, $submissionType);

        // Get Classes to which this Course has been imported
        // TODO - what to do if no gradable assessments for current term?
        $courseImplementationFolders = $this->folderMapper->createCollection();
        foreach ($courseSpecification->getCourseImplementations() as $courseImplementation) {
            $courseFolder = $courseImplementation->getFolder();
            $courseImplementationFolders->setOffset($courseFolder->getId(), $courseFolder);
        }

        /* Get a list of all the students involved in each unit
         * and a list of units involved. This gets reused everywhere
         * so just hit the external db once
         */
        $registry = new Registry($unitImplsInTerm);

        /** @var Term[]|GenericCollection $navigableTerms */
        $navigableTerms = $this->getNavigableTerms($courseSpecification, $ClassFolder);

        $data = [
            'courseSpec'      => $courseSpecification,
            'classFolder'     => $ClassFolder,
            'submissionType'  => $submissionType,
            'submissionTypes' => $this->submissionTypeMapper->findAll(),
            'currentRoute'    => $this->getRouter()->getMatchedRoute()->name,
            'route'           => $routeType,
            'classFolders'    => $courseImplementationFolders,
            'term'            => $term,
            'terms'           => $navigableTerms,
            'students'        => $analysis->getParticipatingStudents(),
            'overallProgress' => $analysis->getOverallProgress(),
            'ranking'         => [
                'top'    => $analysis->getCohortRankings(SORT_DESC),
                'bottom' => $analysis->getCohortRankings(SORT_ASC),
            ],
            'assessments'     => $analysis->getGradableAssessments(false),
            'results'         => [
                'assessments' => $analysis->getGradableAssessments(true),
                'grades'      => $analysis->getAssessmentMarks(true),
            ],
            'progress'        => [
                'allUnits' => $analysis->getProgressByUnit(),
            ],
            'rubrics'         => [
                'allRubrics' => $analysis->getCohortGradesByIndicator($unitImplsInTerm, $registry->getStudents()),
            ],
            'effort'          => [
                'allEffort' => $analysis->getEffort(),
            ],
        ];

        $this->getResponse()->content->set([
            'template' => '@learningAnalytics/course.twig',
            'data'     => $data,
        ]);
    }
}
