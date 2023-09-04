<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan;

use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
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
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
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
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        LatestViewMapper $latestViewMapper,
        Project\ResultCache\Mapper $projectResultCacheMapper,
        LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        private CourseImplementationMapper $courseImplementationMapper,
        private UnitImplementationMapper $unitImplementationMapper,
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
     * @return void
     */
    public function getStatus(IAssessable $assessment): void
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
        $unitAssessmentsTranslator = new Assessment\Translator\UnitAssessments($this->routeGenerator, $student);
        $courseAssessments = [];
        if ($this->getUser()->getRole()->isStaff()) {
            // if we are inside a project get the projects context
            $course = null;
            if ($assessment->getContext()->isImplementationContext()) {
                $unit = $assessment->getContext()->getBaseContext();

                if ($unit instanceof AbstractUnit) {
                    $course = $unit->getCourse();
                }
                $course = $this->courseImplementationMapper->findById($course->getId());
                $course->setUnits($this->unitImplementationMapper->findAllByCourseId($course->getId()));
                foreach ($course->getUnits() as $unitImpl) {
                    /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $unitImpl */
                    $unitImplId = $unitImpl->getId();
                    $options = ['order' => ['sequence' => 'ASC']];
                    $assessments = $this->getAssessmentMapper()->findAllByUnitImplementationIds([$unitImplId], $options);
                    $unitImpl->setAssessables($assessments);
                    $courseAssessments['units'][] = [
                        'id' => $unitImplId,
                        'title' => $unitImpl->getTitle(),
                        'closeDate' => $unitImpl->getDueDate()?->getUsingFormat(DATE_ATOM),
                        'closeDateFormatted'  => $unitImpl->getDueDate()?->getRelativeTime(),
                        'openDate' => $unitImpl->getOpenDate()?->getUsingFormat(DATE_ATOM),
                        'openDateFormatted'  => $unitImpl->getOpenDate()?->getRelativeTime(),
                        'assessments' => $assessments->map($unitAssessmentsTranslator)
                    ];
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
                    $assessments = $this->getAssessmentMapper()->findAllByUnitSpecificationId($unitSpecId, $options);
                    $unitSpec->setAssessables($assessments);
                    $courseAssessments['units'][] = [
                        'id' => $unitSpecId,
                        'title' => $unitSpec->getTitle(),
                        'closeDate' => null, // No Close Date for specifications
                        'closeDateFormatted' => null, // No Close Date for specifications
                        'openDate' => null, // No Open Date for specifications
                        'openDateFormatted' => null, // No Open Date for specifications
                        'assessments' => $assessments->map($unitAssessmentsTranslator)
                    ];
                }
            }
        }

        $twigData = [
            'lastViews'    => $lastView->getTwigViews($assessment, $student, $isViewerAnInstructor),
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'   => $assessment,
            'student'      => $student,
            'returnUrl'    => $returnUrl,
            'courseAssessments' => $courseAssessments,
            'linkedAssessments' => $assessment->getLinkedAssessments() ? $assessment->getLinkedAssessments()->reindex()->map($unitAssessmentsTranslator) : [],

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
        $this->response = $this->responseAsJson($assessment->getLinkedAssessments() ? $assessment->getLinkedAssessments()->reindex()->map(new Assessment\Translator\UnitAssessments()) : []);
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
        // don't link to my self...
        if ($assessment === $linkedAssessment) {
            $problem = (new ApiProblem('Unable to link lesson plan to itself.'))
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
            $this->response = $this->responseAsJson($assessment->getLinkedAssessments()->reindex()->map(new Assessment\Translator\UnitAssessments()));
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
}
