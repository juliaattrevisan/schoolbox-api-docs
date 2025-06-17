<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan;

use Aura\Router\Exception\RouteNotFound;
use Closure;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments\Mapper as LinkedAssessmentsMapper;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

class Controller extends Assessment\Controller
{
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecification\Mapper $courseSpecificationMapper,
        UnitSpecification\Mapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper,
        Project\ResultCache\Mapper $projectResultCacheMapper,
        LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        private CourseImplementation\Mapper $courseImplementationMapper,
        private UnitImplementation\Mapper $unitImplementationMapper,
        private LinkedAssessmentsMapper $linkedAssessmentsMapper
    ) {
        parent::__construct(
            $userMapper,
            $errorMapper,
            $assessmentMapper,
            $workTypeMapper,
            $access,
            $transactor,
            $bakery,
            $termMapper,
            $participantDataSource,
            $courseSpecificationMapper,
            $unitSpecificationMapper,
            $rosettaStone,
            $replicator,
            $latestViewMapper,
            $projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper
        );
    }

    /**
     * Construct an arbitrary array of data for twig rendering
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        return [
            /* This is only used when creating assessments outside of the Course Import
             * workflow. Lesson Plans must always go through Course Import so this var
             * isn't really used at all
             */
            'acceptingResponses' => false,
            'attachzone' => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
        ];
    }


    /**
     * Lesson Plans don't have any feedbacks.
     *
     * @param IAssessable $assessment
     * @param bool $courseView
     * @return
     */
    public function getStatus(IAssessable $assessment, bool $courseView = false)
    {
        // noop
    }


    /**
     * Assessment Summary Screen for a Lesson Plan
     *
     * @param Model|IAssessable $assessment
     * @param User              $student
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSummary(Model|IAssessable $assessment, User $student): void
    {
        /** @var Model $assessment */

        $isViewerAnInstructor = $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder());

        /* Craft a returnUrl, to redirect back to the place from whence you came
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
            $this->getAccess(),
            $this->latestViewMapper,
            new \Schoolbox\External\User(
                $this->getConfigLoader()->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getUserMapper()
            )
        );

        $lastView->addNewView($assessment, $student, $this->getUser());

        /**
         * get list of all activities within this course
         * only for staff
         */
        $unitAssessmentsTranslator = new Assessment\Translator\UnitAssessments(
            $this->routeGenerator,
            $this->getAuthorizer(),
            $student,
            $this->getUser(),
            true
        );
        $courseAssessments = [];
        if ($this->getUser()->getRole()->isStaff()) {
            // if we are inside a project get the projects context
            $course = null;
            if ($assessment->getContext()->isImplementationContext()) {
                //get all courses for the Class Page (Implementation)...
                $folder = $assessment->getContext()->getFolder();
                $courses = $this->courseImplementationMapper->findByFolderId($folder->getId());

                foreach ($courses as $course) {
                    // if the course has been deleted, ignore
                    if ($course->isDeleted()) {
                        continue;
                    }
                    $course->setUnits($this->unitImplementationMapper->findAllByCourseId($course->getId()));
                    foreach ($course->getUnits() as $unitImpl) {
                        /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unitImpl */
                        $unitImplId  = $unitImpl->getId();
                        $options     = ['order' => ['sequence' => 'ASC']];
                        $assessments = $this->getAssessmentMapper()->findAllByUnitImplementationIds([$unitImplId], $options)
                            // Remove Lesson plans, no linking to other lessons
                            ->filter(function ($assessment) {
                                /** @var IAssessable $assessment */
                                return !($assessment instanceof Model);
                            });

                        // hydrate participation
                        $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($assessments);

                        $unitImpl->setAssessables($assessments);
                        $courseAssessments['units'][] = [
                            'id'                         => $unitImplId,
                            'title'                      => $unitImpl->getTitle(),
                            'closeDate'                  => $unitImpl->getDueDate()?->getUsingFormat(DATE_ATOM),
                            'closeDateFormatted'         => $unitImpl->getDueDate()?->getRelativeTime(),
                            'openDate'                   => $unitImpl->getOpenDate()?->getUsingFormat(DATE_ATOM),
                            'openDateFormatted'          => $unitImpl->getOpenDate()?->getRelativeTime(),
                            'relativeCloseDateFormatted' => $unitImpl->getRelativeDueDate()->getRelativeTime(),
                            'relativeOpenDateFormatted'  => $unitImpl->getRelativeOpenDate()->getRelativeTime(),
                            'assessments'                => $assessments->reindex()->map($unitAssessmentsTranslator)
                        ];
                    }
                }
            } else {
                $unit = $assessment->getContext()->getBaseContext();

                if ($unit instanceof AbstractUnit) {
                    $course = $unit->getCourse();
                }
                $course->setUnits($this->unitSpecificationMapper->findAllByCourseId($course->getId()));
                foreach ($course->getUnits() as $unitSpec) {
                    /** @var \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $unitSpec */
                    $unitSpecId = $unitSpec->getId();
                    $options = ['order' => ['sequence' => 'ASC']];
                    $assessments = $this->getAssessmentMapper()->findAllByUnitSpecificationId($unitSpecId, $options)
                        // Remove Lesson plans, no linking to other lessons
                        ->filter(function ($assessment) {
                            /** @var IAssessable $assessment */
                            return !($assessment instanceof Model);
                        });
                    $unitSpec->setAssessables($assessments);
                    $courseAssessments['units'][] = [
                        'id' => $unitSpecId,
                        'title' => $unitSpec->getTitle(),
                        'closeDate' => null, // No Close Date for specifications
                        'closeDateFormatted' => null, // No Close Date for specifications
                        'openDate' => null, // No Open Date for specifications
                        'openDateFormatted' => null, // No Open Date for specifications
                        'relativeCloseDateFormatted' => null, // No Close Date for specifications
                        'relativeOpenDateFormatted' => null, // No Open Date for specifications
                        'assessments' => $assessments->reindex()->map($unitAssessmentsTranslator)
                    ];
                }
            }
        }

        // Re-fetch linked assessments, as their context may not be hydrated.
        // Lesson plan project contexts are deliberately not hydrated, because
        // doing so may lead to infinite loops.
        $linkedAssessmentIds = ($assessment->getLinkedAssessments()?->getPropertyValues('id')) ?? [];
        $linkedAssessments   = null;
        if ($linkedAssessmentIds) {
            $linkedAssessments = $this->getAssessmentMapper()
                ->findAllByIds($linkedAssessmentIds, ['cache' => false,])
                ->sortByCallback(Closure::fromCallable([$this, 'sortLinkedAssessments']));

            // if staff hydrate participation
            if ($this->getUser()->getRole()->isStaff()) {
                $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($linkedAssessments);
            } else {
                // only hydrate for the user who's lesson plan this is
                $this->getAssessmentMapper()->loadParticipantsIntoAssessmentCollection($linkedAssessments, $student);

                // only show the linked assessments that the student is a participant of
                $linkedAssessments = $linkedAssessments->filter(function (IAssessable $assessable) use ($student) {
                    return ($assessable->getParticipantIfExtant($student) instanceof Participant);
                });
            }
        }

        $twigData = [
            'lastViews'         => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment'        => $assessment,
            'student'           => $student,
            'returnUrl'         => $returnUrl,
            'courseAssessments' => $courseAssessments,
            'linkedAssessments' => (
                $linkedAssessments
                ? $linkedAssessments->reindex()->map($unitAssessmentsTranslator)
                : []
            ),

            /* FIXME Controversial:
             *  whether "staff-only fields" really means "instructor-only fields",
             *  or whether it's strictly for the Role::STAFF demographic? Non-staff
             *  roletypes can end up fulfilling Instructor responsibilities
             */
            'isInstructor' => $isViewerAnInstructor,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/lessonPlan/summary/student.twig',
            $twigData
        );
    }

    /**
     * Sort callback for linked assessments.
     *
     * @param IAssessable $a
     * @param IAssessable $b
     * @return int
     */
    private function sortLinkedAssessments(IAssessable $a, IAssessable $b): int
    {
        $aSortValues = $this->deriveSortValues($a);
        $bSortValues = $this->deriveSortValues($b);
        if (isset($aSortValues, $bSortValues)) {
            // Pair sort values together
            foreach ($aSortValues as $i => $aSortValue) {
                $bSortValue = $bSortValues[$i] ?? null;
                if (!isset($bSortValue)) {
                    break;
                }
                if ($aSortValue !== $bSortValue) {
                    return $aSortValue <=> $bSortValue;
                }
            }
        }

        // All else fails: just compare their sequences
        return $a->getSequence() <=> $b->getSequence();
    }

    /**
     * Derive sort values for this assessment.
     *
     * This ordering is supposed to mirror their ordering in either a course
     * builder or course outline:
     * * if a course builder, order by the unit's ID first
     * * if a course outline, order by the unit's due date first
     *
     * Then:
     * * if the assessment is in a project: order by its project's sequence then
     *   the assessment's sequence
     * * otherwise, order by the assessment's sequence
     *
     * @param IAssessable $assessment
     * @return array|null
     */
    private function deriveSortValues(IAssessable $assessment): ?array
    {
        $sortValues  = [];
        $baseContext = $assessment->getBaseContext();
        if ($baseContext instanceof UnitSpecification\Model) {
            $sortValues[] = $baseContext->getId();
        } elseif ($baseContext instanceof UnitImplementation\Model) {
            $sortValues[] = $baseContext->getDueDate()->getUnixTimeStamp();
        } else {
            return null;
        }

        $context = $assessment->getContext();
        if ($context instanceof Project) {
            $sortValues[] = $context->getSequence();
            $sortValues[] = $assessment->getSequence();
        } else {
            $sortValues[] = $assessment->getSequence();
            $sortValues[] = 0;
        }
        return $sortValues;
    }

    /**
     * Lesson Plans don't have any responses.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // noop
    }


    /**
     * Lesson Plans don't have any responses.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // noop
    }

    /**
     * Get the list of all linked assessments for a lesson plan
     *
     * @param Model $assessment
     * @return void
     * @api GET /learning/assessments/{assessmentId}/activities
     */
    public function getActivities(Model $assessment): void
    {
        $this->response = $this->responseAsJson(
            $assessment->getLinkedAssessments()
                ? $assessment->getLinkedAssessments()->reindex()->map(
                    new Assessment\Translator\UnitAssessments(
                        $this->routeGenerator,
                        $this->getAuthorizer(),
                        $this->getUser(),
                        $this->getUser()
                    )
                )
                : []
        );
    }

    /**
     * Joins an assessment to a lesson plan
     * Will ensure you are only joining an assessment from the same course this lesson plan is part of
     *
     * @param Model       $assessment
     * @param IAssessable $linkedAssessment
     * @return void
     * @throws Exception
     * @throws Deleted
     * @api POST /learning/assessments/{assessmentId}/activities/{linkId}
     */
    public function postActivity(Model $assessment, IAssessable $linkedAssessment): void
    {
        // Don't link to lesson plans, this is a bad time
        if ($linkedAssessment instanceof Model) {
            $problem = (new ApiProblem('Unable to link to lesson plans.'))
                ->setStatus(409)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(409);
            return; //exit early
        }
        if ($this->linkedAssessmentsMapper->saveLink($assessment, $linkedAssessment)) {
            // If the first link, then create empty collection
            if (!$assessment->getLinkedAssessments()) {
                $assessment->setLinkedAssessments(new GenericCollection(IAssessable::class));
            }

            $assessment->getLinkedAssessments()->add($linkedAssessment);
            $this->response = $this->responseAsJson(
                $assessment->getLinkedAssessments()->reindex()->map(
                    new Assessment\Translator\UnitAssessments(
                        $this->routeGenerator,
                        $this->getAuthorizer(),
                        $this->getUser(),
                        $this->getUser()
                    )
                )
            );
        } else {
            $problem = (new ApiProblem('Link already Exists'))
                ->setStatus(409)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(409);
            return; //exit early
        }
    }

    /**
     * Remove an assessment link from a lesson plan
     *
     * @param Model       $assessment
     * @param IAssessable $linkedAssessment
     * @return void
     * @throws Exception
     * @api POST /learning/assessments/{assessmentId}/activities/{linkId}
     */
    public function deleteActivity(Model $assessment, IAssessable $linkedAssessment): void
    {
        $return = $this->linkedAssessmentsMapper->deleteLink($assessment, $linkedAssessment);
        if (0 === $return) {
            throw new NotFound();
        }
        $this->response = $this->responseEmpty();
    }

    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @param bool        $isCreate
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false, bool $isCreate = false): string
    {
        /**
         * Always go to the summary screen when a lesson plan has been created,
         * but send the return url with it
         *
         * If the lesson plan was only updated, use the parent logic to determine the redirect instead
         */
        if ($isCreate) {
            $redirect = $this->routeGenerator->generate('learningAssessment.getSummary', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $this->getUser()->getId()
            ]);

            if ($postReturnUrl) {
                $redirect .= '?' . Redirect::RETURN_URL . "=$postReturnUrl";
            }

            return $redirect;
        }

        return parent::determineRedirect($assessment, $postReturnUrl, $defaultReturnUrl, $relocated, $isCreate);
    }
}
